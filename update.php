<?php

    /* This is dumb php script that updates index.php
     * from open-mpi/ompi-release-bot master branch
     * if its content changed */

    // create curl resource
    $ch = curl_init();

    // set url
    curl_setopt($ch, CURLOPT_URL, "https://raw.githubusercontent.com/open-mpi/ompi-release-bot/master/index.php");

    // return the transfer as string
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    // do not check certificate for now ...
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

    // $output contains the output string
    $output = curl_exec($ch);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // close curl resource
    curl_close($ch);

    if ($httpCode == 200) {
        $file = fopen ("index.php", "r") or die ("Unable to open file !");
        $content = fread($file, filesize("index.php"));
        $ctx = hash_init("sha1", HASH_HMAC, "x");
        hash_update($ctx, $content);
        $hash1 = hash_final($ctx);
        
        $ctx = hash_init("sha1", HASH_HMAC, "x");
        hash_update($ctx, $output);
        $hash2 = hash_final($ctx);

        if (strcmp($hash1, $hash2) == 0) {
            print ("NOPE\n");
        } else {
            $file = fopen ("index.php", "w") or die ("Unable to open file !");
            fwrite ($file, $output);
            fclose($file);
            print "OK\n";
        }
    } else {
        print "KO " . $httpCode . "\n";
    }

?>
