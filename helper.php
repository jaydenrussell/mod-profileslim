<?php
/**
 * @package     mod_profileslim
 * @subpackage  Joomla Profile Slim Display
 * @version     1.10.1
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Router\Route;

class ModProfileSlimHelper
{
    private static $cbAvailable = null;

    /**
     * @param int $userId
     * @return string
     * @since 1.2.0
     */
    public static function getDisplayName($userId)
    {
        if (self::cbAvailable()) {
            try {
                $cbUser = CBuser::getInstance((int) $userId, false);
                if ($cbUser) {
                    $cbName = $cbUser->getField('typename', null, 'raw');
                    if (is_string($cbName) && $cbName !== '') {
                        return $cbName;
                    }
                }
            } catch (\Throwable $e) {
                self::log('getDisplayName CB failed: ' . $e->getMessage());
            }
        }

        try {
            $user = Factory::getUser((int) $userId);
            $name = $user->get('name');
            if (is_string($name) && $name !== '') {
                return $name;
            }
            $username = $user->get('username');
            if (is_string($username) && $username !== '') {
                return $username;
            }
        } catch (\Throwable $e) {
            self::log('getDisplayName failed: ' . $e->getMessage());
        }
        return '';
    }

    /**
     * @param int    $userId
     * @param int    $size
     * @param bool   $allowDbFallback
     * @param string $basePath
     * @return string
     * @since 1.4.1
     */
    public static function getAvatar($userId, $size = 32, $allowDbFallback = false, $basePath = '/images/')
    {
        $raw = '';

        if (self::cbAvailable()) {
            try {
                $cbUser = CBuser::getInstance((int) $userId, false);
                if ($cbUser) {
                    // Method A: raw relative path
                    $raw = $cbUser->getField('avatar', null, 'csv');
                    // Method B: parse src from rendered HTML
                    if (empty($raw)) {
                        $html = $cbUser->getField('avatar', null, 'html', 'none', 'profile', 0, false);
                        if (is_string($html)) {
                            if (preg_match('#src="([^"]+)"#i', $html, $m)) {
                                $raw = $m[1];
                            } elseif (preg_match("#src='([^']+)'#i", $html, $m)) {
                                $raw = $m[1];
                            }
                        }
                    }
                    // Method C: direct property
                    if (empty($raw) && !empty($cbUser->avatar)) {
                        $raw = $cbUser->avatar;
                    }
                }
            } catch (\Throwable $e) {
                self::log('getAvatar CB failed: ' . $e->getMessage());
            }
        }

        if ($raw === '') {
            // Try Joomla #__user_profiles for avatar data
            $raw = self::getAvatarFromUserProfiles((int) $userId);
        }

        // DB fallback (opt-in)
        if ($raw === '' && $allowDbFallback) {
            try {
                $db = Factory::getDbo();
                $db->setQuery(
                    $db->getQuery(true)
                        ->select($db->quoteName('profile_value'))
                        ->from($db->quoteName('#__user_profiles'))
                        ->where($db->quoteName('user_id') . ' = ' . (int) $userId)
                        ->where($db->quoteName('profile_key') . ' LIKE ' . $db->quote('%avatar%'))
                );
                $dbAvatar = $db->loadResult();
                if (is_string($dbAvatar) && $dbAvatar !== '' && $dbAvatar !== '0') {
                    $raw = $dbAvatar;
                }
            } catch (\Throwable $e) {
                self::log('getAvatar DB fallback failed: ' . $e->getMessage());
            }
        }

        return self::sanitizeAvatarUrl($raw, $basePath);
    }

    /**
     * Queries Joomla's #__user_profiles table for avatar data.
     * Checks multiple possible profile keys used by Joomla and extensions.
     *
     * @param int $userId
     * @return string
     * @since 1.8.7
     */
    private static function getAvatarFromUserProfiles($userId)
    {
        try {
            $keys = array('avatar', 'profile.avatar', 'user.avatar', 'avatar_url', 'profile_picture');
            $db = Factory::getDbo();
            $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(array('profile_key', 'profile_value')))
                    ->from($db->quoteName('#__user_profiles'))
                    ->where($db->quoteName('user_id') . ' = ' . (int) $userId)
                    ->where($db->quoteName('profile_key') . ' IN (' . implode(',', $db->quote($keys)) . ')')
            );
            $rows = $db->loadAssocList('profile_key');
            if (is_array($rows)) {
                foreach ($keys as $key) {
                    if (isset($rows[$key]['profile_value'])
                        && is_string($rows[$key]['profile_value'])
                        && $rows[$key]['profile_value'] !== ''
                        && $rows[$key]['profile_value'] !== '0') {
                        return $rows[$key]['profile_value'];
                    }
                }
            }
        } catch (\Throwable $e) {
            self::log('getAvatarFromUserProfiles failed: ' . $e->getMessage());
        }
        return '';
    }

    /**
     * Accepts an avatar value that is either a relative path, a URL on
     * the site's own host, or a raw avatar filename. Strips foreign
     * hosts, javascript:/data: schemes, protocol-relative (//),
     * backslashes, and "..".
     *
     * @param string $raw
     * @param string $basePath
     * @return string
     * @since 1.5.1
     */
    private static function sanitizeAvatarUrl($raw, $basePath = '/images/')
    {
        if (!is_string($raw) || $raw === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $raw)) {
            $host = parse_url($raw, PHP_URL_HOST);
            $siteHost = self::siteHost();
            if ($host === null || $siteHost === '' || strcasecmp($host, $siteHost) !== 0) {
                self::log('Avatar rejected: foreign/abs host: ' . $raw);
                return '';
            }
            $path = parse_url($raw, PHP_URL_PATH);
            if (!is_string($path) || $path === '') {
                return '';
            }
            $raw = $path;
        } elseif (preg_match('#^[a-z][a-z0-9+.\\-]*:#i', $raw)) {
            self::log('Avatar rejected: scheme present: ' . $raw);
            return '';
        }
        if (strpos($raw, '//') === 0) {
            self::log('Avatar rejected: protocol-relative: ' . $raw);
            return '';
        }
        if (strpos($raw, '\\') !== false) {
            return '';
        }

        $rel = ltrim($raw, '/');

        if ($rel === '' || !preg_match('#^(?:[a-zA-Z0-9_.-]+/)*[a-zA-Z0-9_.-]+$#', $rel)) {
            self::log('Avatar rejected: invalid path: ' . $raw);
            return '';
        }
        if (strpos($rel, '..') !== false) {
            self::log('Avatar rejected: traversal: ' . $raw);
            return '';
        }

        $base = rtrim($basePath, '/') . '/';
        if (strpos($rel, ltrim($base, '/')) === 0) {
            return $base . substr($rel, strlen(ltrim($base, '/')));
        }
        return $base . $rel;
    }

    /**
     * Returns the site's own HTTP host (no port, lowercased) for same-origin checks.
     *
     * @return string
     * @since 1.5.1
     */
    private static function siteHost()
    {
        if (class_exists('\Joomla\CMS\Uri\Uri')) {
            $h = \Joomla\CMS\Uri\Uri::root();
            $host = parse_url($h, PHP_URL_HOST);
            return is_string($host) ? strtolower($host) : '';
        }
        if (!empty($_SERVER['HTTP_HOST'])) {
            $host = strtolower(preg_replace('/:[0-9]+$/', '', $_SERVER['HTTP_HOST']));
            if (preg_match('#^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$#', $host)
                && !preg_match('#^\d{1,3}(\.\d{1,3}){3}$#', $host)) {
                return $host;
            }
            if (preg_match('#^\[[0-9a-fA-F:]+\]$#', $host)) {
                return $host;
            }
        }
        return '';
    }

    /**
     * Returns the user's profile URL. When Community Builder is installed,
     * builds the explicit CB profile route (CB's router handles the SEF link,
     * no menu binding required). When CB is not installed, falls back to
     * Joomla's native profile link.
     *
     * @param int $userId
     * @return string
     * @since 1.6.0
     */
    public static function profileUrl($userId)
    {
        $userId = (int) $userId;

        if (self::isCbInstalled()) {
            try {
                return Route::_('index.php?option=com_comprofiler&view=userprofile&user=' . $userId);
            } catch (\Throwable $e) {
                self::log('profileUrl CB route failed: ' . $e->getMessage());
            }
        }

        try {
            return Route::_('index.php?option=com_users&view=profile&id=' . $userId);
        } catch (\Throwable $e) {
            self::log('profileUrl failed: ' . $e->getMessage());
        }
        return '';
    }

    /**
     * Strict URL validator for the profile link. Accepts absolute http(s)
     * URLs and safe site-relative paths; rejects javascript:, data:, any
     * other scheme, protocol-relative (//), unsafe characters, and URL-encoded
     * variants of the same. Returns the validated value or an empty string
     * (never an unsafe value).
     *
     * @param string $raw
     * @return string
     * @since 1.5.2
     */
    public static function validateUrl($raw)
    {
        if (!is_string($raw) || $raw === '') {
            return '';
        }
        if (preg_match('#[\x00-\x20<>"\'\\\\]#', $raw)) {
            self::log('Profile URL rejected (unsafe chars): ' . $raw);
            return '';
        }
        if (strpos($raw, '//') === 0) {
            self::log('Profile URL rejected (protocol-relative): ' . $raw);
            return '';
        }
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $raw)) {
            $scheme = strtolower(substr($raw, 0, strpos($raw, ':')));
            if (!in_array($scheme, array('http', 'https'), true)) {
                self::log('Profile URL rejected (disallowed scheme): ' . $raw);
                return '';
            }
        }
        $decoded = rawurldecode($raw);
        if ($decoded !== $raw && preg_match('#[\x00-\x20<>"\']#', $decoded)) {
            self::log('Profile URL rejected (URL-encoded unsafe chars): ' . $raw);
            return '';
        }
        $doubleDecoded = rawurldecode($decoded);
        if ($decoded !== $doubleDecoded && preg_match('#[\x00-\x20<>"\']#', $doubleDecoded)) {
            self::log('Profile URL rejected (double-encoded unsafe chars): ' . $raw);
            return '';
        }
        return $raw;
    }

    /**
     * Strict CSS-value validator for padding/margin params.
     *
     * @param string $raw
     * @return string
     * @since 1.5.2
     */
    public static function validateCss($raw)
    {
        if (!is_string($raw) || $raw === '') {
            return '';
        }
        if (!preg_match('#^[0-9a-z%(). +-]+$#i', $raw)) {
            self::log('CSS value rejected (unsafe chars): ' . $raw);
            return '';
        }
        if (preg_match('#!important#i', $raw)) {
            self::log('CSS value rejected (!important): ' . $raw);
            return '';
        }
        if (preg_match('#[a-z]\s*\(#i', $raw)) {
            self::log('CSS value rejected (function call): ' . $raw);
            return '';
        }
        return $raw;
    }

    /**
     * Validates the configured avatar base directory.
     *
     * @param string $raw
     * @return string
     * @since 1.5.8
     */
    public static function validateBasePath($raw)
    {
        if (!is_string($raw) || $raw === '') {
            return '/images/';
        }
        if (preg_match('#^[a-z][a-z0-9+.\\-]*:#i', $raw)) {
            return '/images/';
        }
        if (strpos($raw, '//') === 0 || strpos($raw, '\\\\') !== false) {
            return '/images/';
        }
        if (!preg_match('#^/[a-zA-Z0-9_./-]+$#', $raw)) {
            return '/images/';
        }
        if (strpos($raw, '..') !== false || strpos($raw, '//') !== false || strpos($raw, './') !== false) {
            return '/images/';
        }
        $normalized = str_replace('/./', '/', $raw);
        if (strpos($normalized, '/./') !== false || preg_match('#/\.$#', $normalized)) {
            return '/images/';
        }
        return rtrim($normalized, '/') . '/';
    }

    /**
     * Lightweight installed check. Whether Community Builder is actually
     * present on this site, WITHOUT triggering full CB API initialization
     * (cbimport, plugin loads). Used to decide between the canonical CB menu
     * resolver and the native Joomla profile link.
     *
     * @return bool
     * @since 1.9.1
     */
    public static function isCbInstalled()
    {
        return file_exists(JPATH_ADMINISTRATOR . '/components/com_comprofiler/plugin.foundation.php')
            || file_exists(JPATH_SITE . '/components/com_comprofiler/comprofiler.php');
    }

    /**
     * Checks if Community Builder is installed and available on this site.
     * Results are cached per request to avoid repeated file checks.
     *
     * @return bool
     * @since 1.8.7
     */
    private static function cbAvailable()
    {
        if (self::$cbAvailable !== null) {
            return self::$cbAvailable;
        }

        $cbFoundation = JPATH_ADMINISTRATOR . '/components/com_comprofiler/plugin.foundation.php';
        if (!file_exists($cbFoundation)) {
            self::$cbAvailable = false;
            return false;
        }

        try {
            include_once $cbFoundation;
        } catch (\Throwable $e) {
            self::log('cbFoundation include failed: ' . $e->getMessage());
        }
        if (function_exists('cbimport')) {
            try {
                cbimport('cb.html');
                cbimport('cb.database');
            } catch (\Throwable $e) {
                self::log('cbimport failed: ' . $e->getMessage());
            }
        }
        try {
            if (isset($GLOBALS['_PLUGINS']) && method_exists($GLOBALS['_PLUGINS'], 'loadPluginGroup')) {
                $GLOBALS['_PLUGINS']->loadPluginGroup('user');
            }
        } catch (\Throwable $e) {
            self::log('loadPluginGroup failed: ' . $e->getMessage());
        }

        if (class_exists('CBuser')) {
            self::$cbAvailable = true;
            return true;
        }

        self::$cbAvailable = false;
        return false;
    }

    /**
     * @since 1.2.1
     */
    private static function log($msg)
    {
        try {
            Log::add('mod_profileslim: ' . $msg, Log::WARNING, 'mod_profileslim');
        } catch (\Throwable $e) {
        }
    }
}