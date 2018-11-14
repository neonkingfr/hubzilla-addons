This plugin is a [flashcard software](https://en.wikipedia.org/wiki/List_of_flashcard_software) that uses [spaced repetition](https://en.wikipedia.org/wiki/Spaced_repetition) as a learning technique.

You can share the flash cards with other users of Hubzilla.

Your learning progress will be kept private.

<img src="/addon/flashcards/view/img/leitner-system.png" align="center" width="70%">

### Two Scenarios to use the Addon...  

- a centralised school like version where the "school" owns the flashcards
- a decentralised version where every learner owns his own copy

In both cases serveral learners can share a box of flashcards, lets say to learn "English-Italian".

Every learner can edit and add cards for "English-Italian". The changes are shared between the learners.

###  Scenario 1: Students use Flashcards of a School

The school and the students all have an account at Hubzilla (Osada). The school has the Flashcards-Addon installed.

#### In Praxis 

Provided the school and the students live on differnt hubs:

- The school on https://school.com/
- A student on https://student.org/

The school has the addon installed at https://school.com/flashcards/school .

The school creates a box of flashcards "English-Italian" and possibly some more. The URL for "English-Italian" could be something like https://school.com/flashcards/school/xy12tlsel89q81o .

The student learns "English-Italian" by opening https://school.com/flashcards/school/xy12tlsel89q81o .

The school and the students can both change or add cards. The syncronization is done automatically as soon as they upload changes.

#### Permissions and Technically

A student sees those flashcards only the school allows him to see. The student will get a copy of "English-Italian". For both users it looks the same, same URL, same content. Everything is done under the hood. The student does not own the flashcards. The school can withdraw the permissions for a student or even delete the flashcards at any time.

### Scenario 2: Two or more Learners share their Flashcards Hub to Hub (planned)

This feature is planned.

Every learner has an account at Hubzilla (Osada) and the Flashcards-Addon installed.

#### In Praxis   

Provided two learners Anna and Bob live on differnt hubs and have the addon installed

- Anna on https://anna.org/flashcards/anna
- Bob on https://bob.org/flashcards/bob

Anna creates a new box of flashcards "English-Italian". The URL could be something like this https://anna.org/flashcards/anna/xy12tlsel89q81o

Bob wants to learn "English-Italian" from Anna. Bob opens his own addon at https://bob.org/flashcards/bob and imports "English-Italian" from Anna using the URL https://anna.org/flashcards/anna/xy12tlsel89q81o .

Both are able to changes or adds cards. The syncronization is done automatically - this time from hub to hub.

#### Permissions and Technically

Bob can only import flashcards from Anna that Anna allows him to see.

If Bob imports "English-Italian" his hub will receive a copy from Annas hub. Bob now owns his copy. The boxes stay connected. The content of "English-Italian" is synchronized from hub to hub as soon as on of them edits or adds a cards. Anna and Bob will see the (language = content) changes of each other. They will never see the learning progress of each other. The learning progress will not leave the hub.

Anna CAN NOT withdraw the permissions for Bob (on Bobs hub) or delete the flashcards of Bob.

Both Anna and Bob can switch off the synchronization from hub to hub. Bob may decide to not merge changes from each other.
