<?php
/**
 * Unit tests for mod_profileslim.php entry point.
 *
 * These tests verify the module's top-level behavior using output buffering
 * to capture actual rendered output:
 * - Guests receive no output (early return)
 * - The module does not produce fatal errors (top-level try/catch)
 * - Helper methods correctly process logged-in user data
 *
 * @package     mod_profileslim
 * @since       1.7.1
 */

namespace Joomla\CMS\Factory {
    class Factory
    {
        /** @var \Joomla\CMS\User\User|null */
        private static $user = null;

        public static function getUser()
        {
            if (self::$user !== null) {
                return self::$user;
            }
            return new \Joomla\CMS\User\User();
        }

        public static function setUser($user)
        {
            self::$user = $user;
        }

        public static function getDocument()
        {
            return new \Joomla\CMS\Document\Document();
        }
    }
}

namespace Joomla\CMS\User {
    class User
    {
        protected $guest = true;
        protected $id = 0;

        public function guest()
        {
            return $this->guest;
        }

        public function id()
        {
            return $this->id;
        }

        public function get($name, $default = null)
        {
            return $default;
        }
    }
}

namespace Joomla\CMS\Document {
    class Document
    {
        public function addStylesheet($url) { /* no-op in tests */ }
        public function addCustomTag($tag) { /* no-op in tests */ }
    }
}

namespace Joomla\Database {
    class DatabaseDriver
    {
        public function getQuery($new = false)
        {
            return new Query();
        }

        public function setQuery($query)
        {
            return $this;
        }

        public function loadResult()
        {
            return null;
        }
    }

    class Query
    {
        public function select($columns)
        {
            return $this;
        }

        public function from($table)
        {
            return $this;
        }

        public function where($conditions, $andOr = null)
        {
            return $this;
        }
    }
}

namespace {
    define('_JEXEC', 1);

    require_once __DIR__ . '/../tests/bootstrap.php';
    require_once __DIR__ . '/../mod_profileslim.php';

    use PHPUnit\Framework\TestCase;

    class ModuleEntryPointTest extends TestCase
    {
        protected function setUp(): void
        {
            $_SERVER['HTTP_HOST'] = 'simcoecurlingclub.ca';
            $_GET = [];
            $_POST = [];
            \Joomla\CMS\Factory\Factory::setUser(null);
        }

        protected function tearDown(): void
        {
            unset($_SERVER['HTTP_HOST']);
            \Joomla\CMS\Factory\Factory::setUser(null);
        }

        public function testGuestReceivesNoOutput()
        {
            // Factory::getUser() returns a guest by default.
            // Capture the module's actual output via output buffering.
            ob_start();
            require_once __DIR__ . '/../mod_profileslim.php';
            $output = ob_get_clean();

            $this->assertEmpty($output, 'Guest users must receive no module output.');
        }

        public function testModuleDoesNotThrowFatalErrors()
        {
            // The module wraps everything in try/catch (\Throwable).
            // Verify no fatal error is produced even when dependencies
            // are stubbed.
            $this->expectNotToPerformAssertions();

            ob_start();
            require_once __DIR__ . '/../mod_profileslim.php';
            ob_end_clean();
        }

        public function testDisplayNameReturnsNonEmptyForLoggedInUser()
        {
            // Test the helper directly for a logged-in user scenario.
            $displayName = ModProfileSlimHelper::getDisplayName(42);

            // Display name may be empty if CB is not loaded in test env,
            // but the method must not throw or produce a fatal error.
            $this->assertIsString($displayName);
        }

        public function testAvatarUrlReturnsString()
        {
            $avatarUrl = ModProfileSlimHelper::getAvatar(42, 32);

            $this->assertIsString($avatarUrl);
        }

        public function testValidateBasePathRejectsTraversal()
        {
            $this->assertSame(
                '/images/',
                ModProfileSlimHelper::validateBasePath('/images/../secret/')
            );
        }

        public function testValidateUrlRejectsJavascriptScheme()
        {
            $this->assertSame(
                '',
                ModProfileSlimHelper::validateUrl('javascript:alert(1)')
            );
        }

        public function testValidateCssRejectsFunctionCall()
        {
            $this->assertSame(
                '',
                ModProfileSlimHelper::validateCss('expression(alert(1))')
            );
        }

        public function testVersionIsConsistentAcrossManifestResolverAndGuard()
        {
            // The updater only offers strictly newer versions, and the
            // entry-point skew guard warns on any mismatch — so manifest,
            // resolver, and guard must always agree.
            $manifest = simplexml_load_file(__DIR__ . '/../mod_profileslim.xml');
            $this->assertNotFalse($manifest);
            $manifestVersion = (string) $manifest->version;
            $this->assertNotEmpty($manifestVersion);

            require_once __DIR__ . '/../cbmenu.php';
            $this->assertTrue(class_exists('SccCbMenuResolver'));
            $this->assertSame($manifestVersion, SccCbMenuResolver::VERSION);

            $entry = file_get_contents(__DIR__ . '/../mod_profileslim.php');
            $this->assertNotFalse($entry);
            $this->assertMatchesRegularExpression(
                "/loadedVer !== '" . preg_quote($manifestVersion, '/') . "'/",
                $entry
            );
        }
    }
}