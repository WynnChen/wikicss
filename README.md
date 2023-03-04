# wikicss
Toolset to help maintain common.css on the wiki

## Installation (On Windows)
1. Download/Clone this project to local.
2. Edit `config.php`, fill in your username and password, save.
3. Install SASS. Please refer to https://sass-lang.com/install
4. Download PHP 8.1 or later. For Windows users, you can download it from here: https://windows.php.net/download/, just use the first Zip link. You may need VC15 or VS16 runtime too, you can find the download link on the left.
5. Unzip the downloaded PHP archive to `wikicss/php` subfolder. There should be a `wikicss/php/php.exe` file now.
done.

## Usage
This toolset provides 5 command line commands that you can use from the command line window.

### pull.bat
This command pull down all source `.scss` files from wiki (under `MediaWiki:Common.css/src/`) to `wikicss/scss` folder. If there already is a `scss` folder, it will be renamed to `scss.old`.

### push.bat
This command push up all source `.scss` files in local `wikicss/scss` folder to the wiki (under `MediaWiki:Common.css/src/`).

### build.bat
This is the post-processer, it will build output `Common.css` and all theme css files. Output files are under `wikicss/output` folder.

### update.bat
This command push up output `Common.css` and theme css files to the wiki, update `MediaWiki:Common.css` as well as `MediaWiki:Theme-Snow` and so on.

### go.bat
build + update.
