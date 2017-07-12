<?php
$phar = new Phar('configChanger.phar');
$phar->buildFromDirectory('changer/');
$phar->setDefaultStub('configChanger.php');
if (Phar::canCompress(Phar::GZ))
{
    $phar->compressFiles(Phar::GZ);
}
else if (Phar::canCompress(Phar::BZ2))
{
    $phar->compressFiles(Phar::BZ2);
}
