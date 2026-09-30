<?php
/**
 * Joomla Profile Slim Display — Standalone Module
 *
 * Outputs: Display Name [avatar] for logged-in users, nothing for guests.
 * Built on Joomla's native profile system for the display name, avatar and
 * profile link. Optional Community Builder awareness: when CB is installed,
 * the profile link is resolved through CB's canonical "View Profile" menu item
 * (CB is a compatibility layer, never a requirement).
 * Top-level try/catch prevents any error from becoming a 500.
 *
 * @version 1.10.0
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;

try {

$user = Factory::getUser();
if ($user->guest) {
    return;
}

require_once __DIR__ . '/helper.php';

$profileItemid = isset($params) ? (int) $params->get('profile_itemid', 0) : 0;

// Canonical CB menu resolver (collision-safe with cblogin-modern-blue).
// Loaded ONLY when Community Builder is actually present; otherwise the
// module renders the native Joomla profile link.
$cbMenu = null;
if (ModProfileSlimHelper::isCbInstalled() && is_file(__DIR__ . '/cbmenu.php')) {
    require_once __DIR__ . '/cbmenu.php';
    if (class_exists('SccCbMenuResolver')) {
        $cbMenu = SccCbMenuResolver::instance();

        // Version-skew guard: the theme may already have loaded an older
        // copy of this class. Fail loudly (log) if it is not ours.
        try {
            $resolverRefl = new \ReflectionClass('SccCbMenuResolver');
            $loadedVer   = $resolverRefl->hasConstant('VERSION') ? (string) $resolverRefl->getConstant('VERSION') : 'unknown';
            if ($loadedVer !== '1.10.0') {
                Log::add('mod_profileslim: loaded SccCbMenuResolver version ' . $loadedVer . ' (expected 1.10.0)', Log::WARNING, 'mod_profileslim');
            }
        } catch (\Throwable $e) {
        }
    }
}

$profileUrl = '';
if ($cbMenu) {
    $profileUrl = $cbMenu->getProfileUrl((int) $user->id, $profileItemid);
}
if ($profileUrl === '') {
    $profileUrl = isset($params) ? ModProfileSlimHelper::validateUrl($params->get('profile_url', '')) : '';
    if ($profileUrl === '') {
        $profileUrl = ModProfileSlimHelper::profileUrl((int) $user->id);
    }
}
$avatarBasePath = isset($params) ? ModProfileSlimHelper::validateBasePath($params->get('avatar_base_path', '/images/')) : '/images/';
if ($avatarBasePath === '') {
    $avatarBasePath = '/images/';
}
$avatarSize   = isset($params) ? (int) $params->get('avatar_size', 32) : 32;
if ($avatarSize < 16 || $avatarSize > 256) {
    $avatarSize = 32;
}
$displayName  = ModProfileSlimHelper::getDisplayName((int) $user->id) ?: $user->get('name');
$avatarUrl    = ModProfileSlimHelper::getAvatar((int) $user->id, $avatarSize, (bool) (isset($params) ? $params->get('avatar_db_fallback', 0) : 0), $avatarBasePath);

$containerPadding = isset($params) ? ModProfileSlimHelper::validateCss($params->get('container_padding', '0 0 0 0')) : '0 0 0 0';
if ($containerPadding === '') {
    $containerPadding = '0 0 0 0';
}
$containerMargin  = isset($params) ? ModProfileSlimHelper::validateCss($params->get('container_margin', '0')) : '0';
if ($containerMargin === '') {
    $containerMargin = '0';
}

$avatarAlign = isset($params) ? $params->get('avatar_align', 'top') : 'top';
switch ($avatarAlign) {
    case 'none':   $alignTransform = 'none'; break;
    case 'center': $alignTransform = 'translateY(50%)'; break;
    case 'bottom': $alignTransform = 'translateY(100%)'; break;
    case 'top':
    default:       $alignTransform = 'translateY(0)'; break;
}

$doc = Factory::getDocument();
$doc->addStylesheet(\Joomla\CMS\Uri\Uri::base() . 'modules/mod_profileslim/css/profile-slim.css');

?>
<div id="ps-header" style="
  --ps-container-padding: <?php echo htmlspecialchars($containerPadding, ENT_QUOTES, 'UTF-8'); ?>;
  --ps-container-margin: <?php echo htmlspecialchars($containerMargin, ENT_QUOTES, 'UTF-8'); ?>;
  --ps-avatar-size: <?php echo (int) $avatarSize; ?>px;
  --ps-avatar-wrap-size: calc(<?php echo (int) $avatarSize; ?>px + 6px);
  --ps-align-transform: <?php echo htmlspecialchars($alignTransform, ENT_QUOTES, 'UTF-8'); ?>;
">
  <a href="<?php echo htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8'); ?>" class="ps-header-link">
    <?php if ($displayName): ?><span class="ps-name"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
    <span class="ps-avatar-wrap">
      <?php if ($avatarUrl): ?>
      <img src="<?php echo htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>" class="ps-avatar" />
      <?php endif; ?>
    </span>
  </a>
</div>
<?php
} catch (\Throwable $e) {
    try {
        Log::add('mod_profileslim: ' . $e->getMessage(), Log::WARNING, 'mod_profileslim');
    } catch (\Throwable $ignored) {
    }

    if (defined('JDEBUG') && JDEBUG) {
        try {
            $debugUser = Factory::getUser();
            if ($debugUser && $debugUser->authorise('core.admin')) {
                echo '<!-- mod_profileslim error: ' . htmlspecialchars((string) $e->getMessage(), ENT_QUOTES, 'UTF-8') . ' -->';
            }
        } catch (\Throwable $ignored) {
        }
    }
}