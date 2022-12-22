<?php


		if(file_exists('keys') == false) {
			mkdir('keys');
		}
		
		

	// Signing pair

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
		
		if(file_exists(KEYDIR.'/sodium_signing_pub.key') == true) {
			unlink(KEYDIR.'/sodium_signing_pub.key');
		}
		if(file_exists(KEYDIR.'/sodium_signing_priv.key') == true) {
			unlink(KEYDIR.'/sodium_signing_priv.key');
		}
		
		file_put_contents(KEYDIR.'/sodium_signing_pub_'.date('YmdHis').'.key', $sSodium_PubBase64);
		file_put_contents(KEYDIR.'/sodium_signing_priv_'.date('YmdHis').'.key', $sSodium_PrivBase64);
		
		
	// Crypto box

		// Let's generate a new cryptobox keypair.
		$keypair = sodium_crypto_box_keypair();
		$privateKey = sodium_crypto_box_secretkey($keypair);
		$publicKey = sodium_crypto_box_publickey($keypair);

		$sSodium_PubBase64 = sodium_bin2base64($publicKey, SODIUM_BASE64_VARIANT_URLSAFE);
		$sSodium_PrivBase64 = sodium_bin2base64($privateKey, SODIUM_BASE64_VARIANT_URLSAFE);
		
		if(file_exists(KEYDIR.'/sodium_cryptobox_pub.key') == true) {
			unlink(KEYDIR.'/sodium_cryptobox_pub.key');
		}
		if(file_exists(KEYDIR.'/sodium_cryptobox_priv.key') == true) {
			unlink(KEYDIR.'/sodium_cryptobox_priv.key');
		}
		
		file_put_contents(KEYDIR.'/sodium_cryptobox_pub_'.date('YmdHis').'.key', $sSodium_PubBase64);
		file_put_contents(KEYDIR.'/sodium_cryptobox_priv_'.date('YmdHis').'.key', $sSodium_PrivBase64);
		
