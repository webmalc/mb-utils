<?php
$phar = new Phar('updater.phar');
$phar->buildFromDirectory('updater/');
$phar->setDefaultStub('globalUpdate.php');
if (Phar::canCompress(Phar::GZ))
{
    $phar->compressFiles(Phar::GZ);
}
else if (Phar::canCompress(Phar::BZ2))
{
    $phar->compressFiles(Phar::BZ2);
}
