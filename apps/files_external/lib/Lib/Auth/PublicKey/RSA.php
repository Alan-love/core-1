<?php
/**
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Files_External\Lib\Auth\PublicKey;

use OCP\Files\External\Auth\AuthMechanism;
use OCP\Files\External\DefinitionParameter;
use OCP\Files\External\IStorageConfig;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUser;
use phpseclib3\Crypt\RSA as RSACrypt;

/**
 * RSA public key authentication
 */
class RSA extends AuthMechanism {
	const CREATE_KEY_BITS = 1024;

	/** @var IConfig */
	private $config;

	public function __construct(IL10N $l, IConfig $config) {
		$this->config = $config;

		$this
			->setIdentifier('publickey::rsa')
			->setScheme(self::SCHEME_PUBLICKEY)
			->setText($l->t('RSA public key'))
			->addParameters([
				(new DefinitionParameter('user', $l->t('Username'))),
				(new DefinitionParameter('public_key', $l->t('Public key'))),
				(new DefinitionParameter('private_key', 'private_key'))
					->setType(DefinitionParameter::VALUE_HIDDEN),
			])
			->addCustomJs('public_key')
		;
	}

	public function manipulateStorageConfig(IStorageConfig &$storage, IUser $user = null) {
		$privateKey = $storage->getBackendOption('private_key');
		$password = $this->config->getSystemValue('secret', '');

		try {
			$rsaKey = RSACrypt::load($privateKey, $password)->withHash('sha1');
		} catch (\phpseclib3\Exception\NoKeyLoadedException $e) {
			throw new \RuntimeException('unable to load private key');
		}

		$storage->setBackendOption('private_key', \base64_encode($privateKey));
		$storage->setBackendOption('public_key_auth', $rsaKey);
	}

	/**
	 * Generate a keypair
	 *
	 * @return array ['privatekey' => $privateKey, 'publickey' => $publicKey]
	 */
	public function createKey() {
		/** @var RSACrypt\PrivateKey $rsaKey */
		$rsaKey = RSACrypt::createKey(self::CREATE_KEY_BITS)
			->withHash('sha1')
			->withMGFHash('sha1');
		$password = $this->config->getSystemValue('secret', '');
		return [
			'privatekey' => $rsaKey->withPassword($password)->toString('PKCS1'),
			'publickey' => $rsaKey->getPublicKey()->toString('OpenSSH')
		];
	}
}
