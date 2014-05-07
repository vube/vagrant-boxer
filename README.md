Vagrant Boxer
=============

[![Build Status](https://travis-ci.org/vube/vagrant-boxer.png?branch=master)](https://travis-ci.org/vube/vagrant-boxer)
[![Coverage Status](https://coveralls.io/repos/vube/vagrant-boxer/badge.png?branch=master)](https://coveralls.io/r/vube/vagrant-boxer?branch=master)
[![Latest Stable Version](https://poser.pugx.org/vube/vagrant-boxer/v/stable.png)](https://packagist.org/packages/vube/vagrant-boxer)
[![Dependency Status](https://www.versioneye.com/user/projects/5361301bfe0d07fa670000b3/badge.png)](https://www.versioneye.com/user/projects/5361301bfe0d07fa670000b3)

Application to manage boxing up Vagrant VMs to be used as base boxes for use in
private box distribution systems.


Features
--------

- Automatically package up Virtual Machines into reusable base boxes for use
by other Vagrant configs.

- Automatically create/update [vagrant-catalog](https://github.com/vube/vagrant-catalog)
metadata.json files so you can run your own private internal Vagrant Cloud.

- Automatically upload VM box images and metadata files to your file server.


Installation
------------

To install, clone this repository and put it wherever you want it to live on
your machine.

```bash
$ git clone https://github.com/vube/vagrant-boxer
```

Then you can optionally symlink something like /usr/local/bin/boxer.php
to this so it will run from your path just by typing `boxer.php`

```bash
$ sudo ln -sf /path/to/vagrant-boxer/boxer.php /usr/local/bin/boxer.php
```


Example Usage
-------------

vagrant-boxer works either from command line switches or using a `boxer.json` config
file.  Here are examples of both.


### Usage via command line

Note that the `{BASENAME}` below should be substituted with the name of the VM in your
VirtualBox list.

```bash
$ boxer.php --verbose --base "{BASENAME}" --boxer-id "your-company/{BASENAME}"  --major-version 1.0 --url-prefix "http://your-file-server.com/" --upload-base-uri "username@your-file-server.com:/path/to/docroot"
```


### Usage via boxer.json

Here again, `{BASENAME}` should be substituted with the name of the VM in your
VirtualBox list.

#### Running boxer.php using boxer.json

```bash
$ boxer.php --verbose --config-file /path/to/boxer.json
```

#### Contents of boxer.json

```json
{
    "vm-name": "{BASENAME}",
    "boxer-id": "your-company/{BASENAME}",
    "version": "1.0",
    "download-url-prefix": "http://your-file-server.com/",
    "upload-base-uri": "username@your-file-server.com:/path/to/docroot"
}
```


Dependencies
------------

- PHP 5.3.2+
- Composer
