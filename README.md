# Witangone

Witango/TeraScript-to-PHP compiler. TeraScript files go in, PHP files come out.

The goal is to git rid of TeraScript code in an automated fashion. While it handles much of the syntax, it is still a little way from being a silver bullet (see status below). However if you are stuck with TeraScript and are desperate, please send me a message! I'd love to help.

The name was given back when TeraScript was called Witango. TeraGone doesn't have the same ring to it...

## Status

I'm working toward a specific goal at the moment: to convert the handful of TeraScript applications we are maintaining. This means the compiler will support a specific subset of TeraScript that makes sense for us. The subset of the language used by your code is probably different. I would love to build a complete compiler, but once our goal is achieved there is little incentive to continue what truly is a laborious effort in my free time. Again if you truly need this I would love to work with you to build support for the functionality specifically needed by your code.

## Coverage

This is a moving target but is improving quickly. I currently can compile 97% of the 115 files in one of our applications. Most of the issues are fairly small at the moment and I expect to be at 100% shortly. When work settles down I'll list out the specific language featues that are supported.

## Usage

So you want to give it a try? Knock yourself out! Please post the results so we can make improvements.

First create a new directory where you'd like your compiled PHP code to go and `cd` to it.

    cd /to/your/new/dir

Next initialize your new project. This will configure dependencies and create a`bootstrap.php` file, used for configuration and startup code. Composer is used to install dependencies.

    php witangone.php init

    composer install

Finally, specify a source folder or file. The resulting PHP code will be placed in the current directory.

    php witangone.php compile [source-dir or file]

Witangone will compile as many files as possible and will ignore any actions and meta tags it doesn't understand. It will also take note of those actions and meta tags and give a summary of what it skipped and how many times. This information is useful in deciding what to work on.

## Contact

Not everything is covered here. Feel free to hit me up via email (alex@logicallydunn.com) or the issue tracker (if you have an issue or suggestions).