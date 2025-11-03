<?php

namespace App\Console\Commands;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Console\Command;
use Laravel\Dusk\Browser;

class HccTestLogin extends Command
{
    protected $signature = 'hcc:test-login';
    protected $description = 'Test HCC login with manual verification';

    public function handle()
    {
        $this->info("Testing HCC login...");

        $driver = $this->createDriver();
        $browser = new Browser($driver);

        try {
            // Go to login page
            $this->info("Going to login page...");
            $browser->visit('https://www.hik-connect.com/views/login/index.html#/login')
                ->pause(3000);

            // Click Phone tab
            $this->info("Clicking Phone tab...");
            $browser->script("
                var phoneTab = document.querySelector('#HCBLogin > div > div:nth-child(1) > div.login-container > div > nav > div.login-form > form > div:nth-child(1) > div > div > label:nth-child(2)');
                if (phoneTab) {
                    console.log('[HCC] Clicking phone tab');
                    phoneTab.click();
                } else {
                    console.log('[HCC] Phone tab not found');
                }
            ");
            $browser->pause(1000);

            // Fill phone number
            $this->info("Filling phone number...");
            $browser->script("
                var phoneInput = document.querySelector('#HCBLogin > div > div:nth-child(1) > div.login-container > div > nav > div.login-form > form > div:nth-child(2) > div > div > input');
                if (phoneInput) {
                    console.log('[HCC] Filling phone number');
                    phoneInput.value = '03322414255';
                    phoneInput.dispatchEvent(new Event('input', { bubbles: true }));
                } else {
                    console.log('[HCC] Phone input not found');
                }
            ");
            $browser->pause(1000);

            // Fill password
            $this->info("Filling password...");
            $browser->script("
                var passwordInput = document.querySelector('input[type=\"password\"]');
                if (passwordInput) {
                    console.log('[HCC] Filling password');
                    passwordInput.value = 'Alrehman123';
                    passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
                } else {
                    console.log('[HCC] Password input not found');
                }
            ");
            $browser->pause(1000);

            // Click login button
            $this->info("Clicking login button...");
            $browser->script("
                var loginSubmitBtn = document.querySelector('#HCBLogin > div > div:nth-child(1) > div.login-container > div > nav > div.login-form > form > div:nth-child(5) > div > button');
                if (loginSubmitBtn) {
                    console.log('[HCC] Clicking login submit button');
                    loginSubmitBtn.click();
                } else {
                    console.log('[HCC] Login submit button not found');
                }
            ");
            $browser->pause(5000);

            // Check result
            $currentUrl = $driver->getCurrentURL();
            $this->info("Current URL: " . $currentUrl);

            $pageContent = $browser->script("return document.body.textContent;");
            $this->info("Page content: " . substr($pageContent[0] ?? '', 0, 200));

            // Check for error messages
            $errorCheck = $browser->script("
                var errorMsg = document.querySelector('.el-message, .error, [class*=\"error\"], [class*=\"message\"]');
                return errorMsg ? errorMsg.textContent : 'No error message found';
            ");

            $this->info("Error check: " . ($errorCheck[0] ?? 'No error'));

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        } finally {
            $browser->quit();
        }

        return Command::SUCCESS;
    }

    protected function createDriver()
    {
        $options = (new ChromeOptions)->addArguments([
            '--disable-gpu',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--window-size=1920,1080',
        ]);

        return RemoteWebDriver::create(
            config('hcc.dusk_driver_url', 'http://localhost:9515'),
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }
}

