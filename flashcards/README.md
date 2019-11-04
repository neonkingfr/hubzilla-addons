This addon is a [flashcard software](https://en.wikipedia.org/wiki/List_of_flashcard_software) that uses [spaced repetition](https://en.wikipedia.org/wiki/Spaced_repetition) as a learning technique.

You can share the flash cards with other users of Hubzilla and ZAP.

Your learning progress will be kept private.

<img src="/addon/flashcards/view/img/leitner-system.png" align="center" width="70%">

### In Praxis - The School Example

#### Introduction

A school and its students all have an account at Hubzilla or ZAP. The school has the addon Flashcards installed. The URL is https://school.com/flashcards/school .

It is possible that the school and the students have accounts on different instances. let's say

- The school on https://school.com/
- A student on https://student.org/

#### How to begin?

The school...

- opens the addon https://school.com/flashcards/school
- creates a box of flashcards "English-Italian". This box a available at for example https://school.com/flashcards/school/xy12tlsel89q81o
- adds title, description and some card.
- saves the box
- sends the URL to the student https://school.com/flashcards/school/xy12tlsel89q81o

The student wants to learn "English-Italian" and
- opens https://school.com/flashcards/school/xy12tlsel89q81o
- starts to learn
- saves the box
- can now continue to learn on all other devices by opening https://school.com/flashcards/school/xy12tlsel89q81o again

#### How to fix errors in the cards?

The school and the students can both add or modify cards. The syncronization is done automatically as soon as they upload (save) changes.

### Permissions and Technically

A student sees those flashcards only the school allows him to see. The student will get a copy of "English-Italian". For both users it looks the same, same URL, same content. Everything is done under the hood. The student does not own the flashcards. The school can withdraw the permissions for a student or even delete the flashcards at any time.

### Federation

It is not tested yet but should be available in the near future. If enabled a student will be able to use the addon without having an account at Hubzulla or ZAP as long as the account supports [ActivityPub](https://en.wikipedia.org/wiki/ActivityPub). Mastodon users have this for example. Stay tuned.
