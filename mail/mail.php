<?php

use Zotlabs\Extend\Route;

/**
 * Name: Mail
 * Description: Deprecated former core mail app. It is not possible to send mail anymore with this interface. It is read/delete only!
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
