# SMF 2.0 to phpBB 3.2 convertor

Based from [this convertor](https://www.phpbb.com/community/viewtopic.php?p=13219422#p13219422), most credits due to its authors, the instructions are mostly the same, I only made it work with phpBB 3.2.

The SMF authentication provider that comes with this convertor (so the passwords will get converted on the fly) now comes in an extension, please enable it in the ACP before proceeding to the conversion or you might get locked out.

So to recap...

## Instructions

1. Install your phpBB board
2. Rename the install folder to something else
3. Upload the extension in the ext folder from this repo to the correct location and enable it in the ACP
4. Rename the install folder back
5. Upload the files in the install folder from this repo to the correct location
6. Run the convertor in the installer
7. Delete the install folder
8. Have fun
