<?php

use Zotlabs\Extend\Route;

/**
 * Name: Flashcards
 * Description: Flashcard software that uses spaced repetition as a learning technique.
 * Version: 1.2.2
 * Author: Tom Wiedenhöft (ojrandom@protonmail.com)
 * Maintainer: Tom Wiedenhöft (ojrandom@protonmail.com)
 *
 */
function flashcards_load() {
    Route::register('addon/flashcards/Mod_Flashcards.php', 'flashcards');
}

function flashcards_unload() {
    Route::unregister('addon/flashcards/Mod_Flashcards.php', 'flashcards');
}
