omeka-solr-shim-theme
=====================

This is the main portion of the Omeuka application, which serves
ExploreUK to users atop Omeka, pulling metadata from Solr.

This is based in part on euk: https://github.com/uklibraries/euk/ .

Installation
------------

Modify the Omeka installation's .htaccess file to include the
stanza included in the following file:

* htaccess-stanza.txt

Run the script exe/build.sh .  This will wipe out and recreate
the dist directory, then build the file

* dist/omeuka.tar.gz

Extract this file in the Omeka root directory.

Log in to Omeka as a Super User.  Select the Omeuka Prologue theme,
then configure the theme appropriately.

Licenses
--------

Copyright (C) 2018 Michael Slone.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.

The following directories are derived from the HTML5 Up Prologue theme,
which is licensed under the Creative Commons Attribution 3.0 License:

* omeuka/assets
* omeuka/images

Additionally, the HTML in the following directory is derived from the
HTML5 Up Prologue theme:

* omeuka/templates

This package includes some packages with other licenses:

* Internet Archive BookReader - GNU Affero GPL
* jQuery - dual-licensed under the GPLv2 and MIT licenses
* jQuery UI - dual-licensed under the GPL and MIT licenses
* MediaElement.js - MIT
* OpenSeadragon - new BSD license
