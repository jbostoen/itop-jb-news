<?php

    $message = 'Hello, this is a secure message signed by Alice.';

    // Let's generate a new signing keypair for Alice.
    $aliceKeypair = sodium_crypto_sign_keypair();
    // And extract Alice's private key to use for signing.
    $alicePrivateKey = sodium_crypto_sign_secretkey($aliceKeypair);
    // And extract Alice's public key to separately send to Bob.
    $alicePublicKey = sodium_crypto_sign_publickey($aliceKeypair);

    // Now we can sign the message.
    $signature = sodium_crypto_sign_detached($message, $alicePrivateKey);

    // The message can be transmitted to Bob, who can verify the signature using Alice's public key.
    if (sodium_crypto_sign_verify_detached($signature, $message, $alicePublicKey)) {
        echo 'The message is authentic from Alice.';
    } else {
        echo 'The message has been tampered with!';
    }   


	$sSodium_PubBase64 = sodium_bin2base64($alicePublicKey, SODIUM_BASE64_VARIANT_URLSAFE);
	$sSodium_PrivBase64 = sodium_bin2base64($alicePrivateKey, SODIUM_BASE64_VARIANT_URLSAFE);
	
	if(file_exists('keys') == false) {
		mkdir('keys');
	}
	
	if(file_exists(KEYDIR.'/sodium_pub.key') == true) {
		unlink(KEYDIR.'/sodium_pub.key');
	}
	if(file_exists(KEYDIR.'/sodium_priv.key') == true) {
		unlink(KEYDIR.'/sodium_priv.key');
	}
	
	file_put_contents(KEYDIR.'/sodium_pub_'.date('YmdHis').'.key', $sSodium_PubBase64);
	file_put_contents(KEYDIR.'/sodium_priv_'.date('YmdHis').'.key', $sSodium_PrivBase64);		
	
