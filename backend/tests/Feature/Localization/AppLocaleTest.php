<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AppLocaleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Locale probe : route éphémère exposée uniquement pour la suite de tests.
        // Elle est enregistrée sous le middleware group `api` afin que le middleware
        // `SetLocale` (cible de la story FIX-22.1) s'applique. Elle renvoie la locale
        // active au moment où le handler s'exécute (donc après la pile middleware).
        Route::middleware('api')->get('/__locale-probe', fn () => response()->json([
            'locale' => App::getLocale(),
        ]));
    }

    /**
     * Test 1.A — AC #1 : le défaut de config doit être 'fr'.
     *
     * Isole UNIQUEMENT `config/app.php:87`. On neutralise toute valeur `APP_LOCALE`
     * provenant de `.env`, `.env.testing` ou `phpunit.xml` puis on évalue le fichier
     * `config/app.php` à neuf : la valeur retournée pour `locale` doit être `'fr'`
     * (le défaut codé en dur dans le fichier de config), peu importe l'environnement.
     *
     * Aujourd'hui : `config/app.php:87` est `env('APP_LOCALE', 'en')` → ce test échoue
     * (le défaut résolu sans env est `'en'`). Note : `App::getLocale()` ou
     * `config('app.locale')` ne suffisent pas comme témoin car `.env.testing` met
     * `APP_LOCALE=fr` et masque le défaut du fichier de config.
     */
    public function test_config_default_locale_is_fr(): void
    {
        $previousEnv = $_ENV['APP_LOCALE'] ?? null;
        $previousServer = $_SERVER['APP_LOCALE'] ?? null;
        $previousGetenv = getenv('APP_LOCALE');

        unset($_ENV['APP_LOCALE'], $_SERVER['APP_LOCALE']);
        putenv('APP_LOCALE');

        try {
            $config = require base_path('config/app.php');

            $this->assertSame(
                'fr',
                $config['locale'],
                "Le défaut codé dans config/app.php:87 doit être 'fr' (et non 'en')."
            );
        } finally {
            if ($previousEnv !== null) {
                $_ENV['APP_LOCALE'] = $previousEnv;
            }
            if ($previousServer !== null) {
                $_SERVER['APP_LOCALE'] = $previousServer;
            }
            if ($previousGetenv !== false) {
                putenv('APP_LOCALE='.$previousGetenv);
            }
        }
    }

    /**
     * Test 1.B — AC #2, #9 : le middleware SetLocale force `fr` même si le caller
     * a explicitement positionné la locale à `en` avant la requête.
     *
     * On force `App::setLocale('en')` avant l'appel HTTP. Si le middleware existait
     * et était bien enregistré sur le groupe `api`, la locale serait remise à `fr`
     * en début de pipeline et la route renverrait `{"locale":"fr"}`. Sans middleware,
     * la locale reste celle posée par le test (`en`) et la réponse est `{"locale":"en"}`.
     *
     * Aujourd'hui : pas de middleware → ce test échoue (réponse contient `en`).
     */
    public function test_api_group_middleware_forces_fr_even_when_caller_sets_en(): void
    {
        App::setLocale('en');

        $response = $this->getJson('/__locale-probe');

        $response->assertOk()->assertJson(['locale' => 'fr']);
    }

    /**
     * Test 1.C — AC #4 : `trans('validation.required')` doit retourner la version FR.
     *
     * Aujourd'hui : `backend/lang/fr/validation.php` n'existe pas → `trans()` retombe
     * sur le vendor EN (`"The :attribute field is required."`) ou retourne la clé brute
     * `'validation.required'` selon la config du translator. Dans tous les cas, la chaîne
     * exacte FR n'est pas atteignable → ce test échoue.
     */
    public function test_trans_resolves_validation_required_in_french(): void
    {
        $message = trans('validation.required', ['attribute' => 'email']);

        $this->assertSame('Le champ email est obligatoire.', $message);
    }

    /**
     * Test 1.D — AC #11 : `trans('passwords.throttled')` doit retourner la version FR.
     *
     * Aujourd'hui : `backend/lang/fr/passwords.php` n'existe pas → on retombe sur EN
     * (`"Please wait before retrying."`) → ce test échoue.
     */
    public function test_trans_resolves_passwords_throttled_in_french(): void
    {
        $message = trans('passwords.throttled');

        $this->assertSame('Veuillez patienter avant de réessayer.', $message);
    }

    /**
     * Test 1.E — AC #3, #11 : couverture exhaustive des clés vendor publiées.
     *
     * Pour chacun des 4 fichiers vendor (`validation`, `auth`, `passwords`, `pagination`),
     * compare l'ensemble des clés aplaties EN et FR : doit être strictement identique
     * (ordre ignoré — la brittleness d'ordre n'apporte aucune garantie de couverture).
     * Vérifie que chaque leaf scalaire FR est une chaîne non vide ET qu'il diffère du
     * leaf EN au même chemin (hors clé documentaire `custom.attribute-name.rule-name`
     * qui est un placeholder de structure, pas une traduction).
     *
     * Aujourd'hui : ni `lang/en/` ni `lang/fr/` n'existent → `require` lance une
     * `ErrorException` → ce test échoue (status fail/error, peu importe).
     */
    public function test_fr_lang_files_cover_every_published_en_key(): void
    {
        $files = ['validation', 'auth', 'passwords', 'pagination'];

        foreach ($files as $file) {
            $enPath = base_path('lang/en/'.$file.'.php');
            $frPath = base_path('lang/fr/'.$file.'.php');

            $this->assertFileExists($enPath, "Le fichier vendor publié lang/en/{$file}.php est requis.");
            $this->assertFileExists($frPath, "Le fichier de traduction lang/fr/{$file}.php est requis.");

            /** @var array<string,mixed> $en */
            $en = require $enPath;
            /** @var array<string,mixed> $fr */
            $fr = require $frPath;

            $flatEn = $this->flattenKeys($en);
            $flatFr = $this->flattenKeys($fr);

            $missingInFr = array_diff(array_keys($flatEn), array_keys($flatFr));
            $extraInFr = array_diff(array_keys($flatFr), array_keys($flatEn));

            $this->assertSame(
                [],
                array_values($missingInFr),
                "lang/fr/{$file}.php est incomplet — clés EN absentes : ".implode(', ', $missingInFr)
            );
            $this->assertSame(
                [],
                array_values($extraInFr),
                "lang/fr/{$file}.php contient des clés inconnues côté EN : ".implode(', ', $extraInFr)
            );

            foreach ($flatFr as $key => $value) {
                $this->assertTrue(
                    is_string($value) && trim($value) !== '',
                    "La clé '{$key}' dans lang/fr/{$file}.php doit être une chaîne FR non vide."
                );

                // Heuristique anti-oubli : un leaf FR strictement identique au leaf EN
                // signale une traduction manquante. Exception : le placeholder documentaire
                // `custom.attribute-name.rule-name` dans `validation.php` ne porte pas de
                // sens linguistique — Laravel le publie uniquement pour montrer la forme.
                if ($file === 'validation' && $key === 'custom.attribute-name.rule-name') {
                    continue;
                }

                $this->assertNotSame(
                    $flatEn[$key] ?? null,
                    $value,
                    "La clé '{$key}' dans lang/fr/{$file}.php est identique à l'EN — traduction manquante ?"
                );
            }
        }
    }

    /**
     * Test 1.F — les clés mail/notification utilisées par les templates Blade vendor sont
     * résolues en FR depuis `lang/fr.json`.
     *
     * Les templates `vendor/laravel/framework/src/Illuminate/Notifications/resources/views/email.blade.php`
     * et `vendor/laravel/framework/src/Illuminate/Mail/resources/views/html/message.blade.php` appellent
     * `@lang('Hello!')`, `@lang('Regards,')`, `__('All rights reserved.')`, etc. Ces clés sont
     * résolues contre `lang/fr.json` — si le fichier est absent, Laravel renvoie la clé
     * brute (anglais). Ce test atteste que les traductions FR sont présentes.
     */
    public function test_vendor_mail_template_json_keys_are_translated_in_french(): void
    {
        $keys = [
            'Hello!',
            'Whoops!',
            'Regards,',
            'All rights reserved.',
            "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\ninto your web browser:",
        ];

        $jsonPath = base_path('lang/fr.json');
        $this->assertFileExists($jsonPath, 'Le fichier lang/fr.json est requis pour traduire les templates mail vendor.');

        /** @var array<string,string> $translations */
        $translations = json_decode((string) file_get_contents($jsonPath), true, flags: JSON_THROW_ON_ERROR);

        foreach ($keys as $key) {
            $this->assertArrayHasKey(
                $key,
                $translations,
                "La clé JSON '{$key}' doit être présente dans lang/fr.json."
            );

            $value = $translations[$key];
            $this->assertTrue(
                is_string($value) && trim($value) !== '' && $value !== $key,
                "La clé JSON '{$key}' dans lang/fr.json doit porter une traduction FR non vide et distincte de la clé."
            );

            $this->assertSame(
                $translations[$key],
                trans($key),
                "trans('{$key}') doit résoudre vers la valeur FR de lang/fr.json."
            );
        }
    }

    /**
     * Aplatit récursivement un tableau de traductions en `key.subkey.subsubkey` => leaf.
     * Les sous-tableaux vides (ex. `validation.custom`, `validation.attributes`) ne
     * produisent aucune clé aplatie — on accepte donc qu'ils restent `[]` côté FR
     * tant qu'ils restent `[]` côté EN.
     *
     * @param  array<string,mixed>  $array
     * @return array<string,mixed>
     */
    private function flattenKeys(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $compositeKey = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenKeys($value, $compositeKey));
            } else {
                $result[$compositeKey] = $value;
            }
        }

        return $result;
    }
}
