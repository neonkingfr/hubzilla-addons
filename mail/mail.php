<?php

use Zotlabs\Extend\Route;

/**
 * Name: Mail
 * Description: Former core mail app. Still required for Diaspora conversations. 
 * Version: 1.0
 * Author: Mario Vavti
 * Maintainer: None
 *
 */

function mail_load() {
    Route::register('addon/mail/Mod_Mail.php', 'mail');
}

function mail_unload() {
    Route::unregister('addon/mail/Mod_Mail.php', 'mail');
}
