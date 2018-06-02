BCSG Test - Notes
=================
I have deliberately over engineered this simple application to demonstrate the use of how
it could work in the context of a wider system.

Installation
============
Once extracted / downloaded, the Uploads folder needs to be writeable to the webserver to
accept the CSV files. On Windows this should be OK, but under Linux/Mac, the easiest way is to 
make the folder permissions 777 using: chmod 777 banking/uploads

Once installed, simply navigate to index.php where you should be presented with forms for both 
uploading a CSV file and for adding records one by one.

Dependencies - Font Awesome
===========================
Although the application is designed to run locally without a connection to the Internet,
I do reference a font awesome CDN to supply some styling refinements. Namely, the drop down arrows
on the form selectors. Because this is an online resource, if no Internet connection is present
you may not see these as intended, or your browser will replace them with unknown characters.

Another library I have referenced is JQuery, but this is included in the package so should not
be affected by working offline.

Browser support
===============
I have developed this application within Firefox developer edition, the JavaScript parts are
object oriented ES6 which is suported by most up to date browsers, but you may experience issues
on older browsers.

Assumptions
===========
I have assumed that duplicate entries of credit card numbers will not be allowed, so in the case
of manual entry, an alert will show. In the case of CSV upload, it will ignore duplicates as it 
loops through the rows.

CSV format - example
====================
HSBC Canada,5601-2345-3446-5678,Oct-2018
Royal Bank of Canada,4519-4532-4524-2456,Nov-2018
American Express,3786-7334-8965-345,Dec-2019

Operation
=========
I have built the app to use JS Ajax for both manual entry and uploading of CSVs, refreshing the 
credit card list each time either of these is actioned.

Javascript validation ensure forms are correctly completed and then PHP validation ensure the values
are correctly formatted. The PHP validation simply ensure that the credit card number is more than
16 characters in length, but does not specify a maximum length or that the numbers are correctly
formatted.

For CSV imports, rows with numbers that do not match this simple validation will be ignored.

PHP version
===========
I have written this under PHP7.0 (Ubuntu) but the code is backwards compatible to at least PHP 5.3
and does not require any additional support libraries such as PECL or PEAR.

The framework
=============
This is a cut down version of a custom built framework that I have developed. I have included only
the parts needed to run this application, other parts of the framework such as Database, User
session handling and logging functions have been removed. Ive included a brief description of each part:

class.func.php - this is a general purpose class for handling static methods that are useful throughout
the system but dont necessarily fit within the remit of a webapp.

class.registry.php - this is an overriding class that allows the programmer to control acces to
instatiated objects elsewhere within the system.

class.session.php - Is a wrapper for PHPs standard session handling, to make it easier to create,
access and store sessions. Can be Database backed or use PHPs standard session handler.

class.theme.php - This is a compiler that forces separation between PHP and UI (HTML/CSS/JS) content.
It allows for division of labour within teams so that UI experts can concentrate on the UI elements
without worrying about the underlying PHP architecture.

Modules
=======
The architecture of the framework allows for new mnodules to be added easily. Only the required module
is loaded into memory during execution which allows for a lean memory footprint. The index.php
acts as the controller for loading the module into memory and executing it.

index.php is the only part of the application that has procedural code, and is used to invoke the 
necessary objects that form the whole system.

I have included a debug.php script which I use to display errors to the screen to allow for easier
resolution, all warnings and bugs are output to screen to help the programmer fix the bugs as they
are introduced to cut down on the test cycle times.

How it works
============
index.php looks for the presence of a module code and, if absent defaults to the "home" module.
This is loaded in and initialised with hte init method, where a selection of methods can be
accessed via the URL. On entry, the default method "display()" is called which outputs the UI
to the screen. Subsequent ajax calls to other methods within the home module are then triggered
which render the credit card list to the ajax parser for display to the screen.

The credit cards are "databased" using a Session wrapper around PHPs built in session handling
functions.