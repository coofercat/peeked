Peeked - A Web-Based Content Editor for Pico
============================================

Provides a Git-connected online Markdown editor and file manager for Pico.

Install
-------

Either:
* Clone the Github repo into your 'plugins' directory (so you get a 'peeked' subdirectory)
Or:
* Extract the zip into your 'plugins' directory

Then:

1. Open the pico_editor_config.php file and insert your sha1 hashed password
2. Visit http://www.yoursite.com/admin and login
3. Update the permissions if needed.
4. Thats it :)

About
-----

Peeked provides an online, web browser based means to edit Pico page content. Additionally, it has the ability to perform some basic Git operations such as commit, push/pull etc.

The general use-case for Peeked is for one or more content editors to have a Git repo cloned onto their laptops. They can then go ahead and create or edit content, saving it to their local machine as required. When they're happy, they can commit to their local Git repo. How they publish the content is up to the administrator, but one method is to have a post-update hook on the Git server that publishes the content into the DocumentRoot of the webserver(s). Obviously, editors can Git-pull the changes other editors have made to their local machines so that they stay up to date. Depending on how you set things up, it's possible editors could even edit pages directly on the public website (and commit those to the Git repo from there).

Git features are only shown in the editor UI if the server has a Git binary available, and the content is in a Git repo. Push/pull functions are only available if the repo has one or more remote servers configured into it.

History
-------

Peeked is a fork + modifications of the [Pico Editor](https://github.com/gilbitron/Pico-Editor-Plugin), written by [Gilbert Pellegrom](https://github.com/gilbitron). It contains a few bug fixes and some functional changes, most particularly the addition of some Git capabilities.

