This repository contains some Tooling to dump static informations about Shopware versions like:

* API Documentation: https://api-doc.friendsofshopware.com/
* MD5 of all Shopware files


# File Checker

This Tool can be used to check the integrity of the Installation. It checks all MD5Sums of the Installation with the Original Hash.

Usage:

```bash
cd [ToYourInstallation]
wget https://raw.githubusercontent.com/FriendsOfShopware/api-doc/master/file-checker.php
php file-checker.php
rm file-checker.php
```
