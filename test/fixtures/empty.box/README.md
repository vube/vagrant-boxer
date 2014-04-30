# empty.box fixture

PHP's Phar extension does not work with VFSStream, so we're required
to actually have files on a filesystem.

Thus we have these files that are used to create an empty.box which
is basically just a .tar.gz of this directory.

See test/unit/Vube/VagrantBoxer/BoxerTest.php and search for empty.box
to see how/where this is used.
