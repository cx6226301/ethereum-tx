<?php

namespace Web3p\EthereumTx;

use InvalidArgumentException;
use RuntimeException;
use Web3p\RLP\RLP;
use Elliptic\EC;
use Elliptic\EC\KeyPair;
use ArrayAccess;
use Web3p\EthereumUtil\Util;

/**
 * It's a instance for generating/serializing ethereum transaction.
 * 
 * ```php
 * use Web3p\EthereumTx\Transaction;
 * 
 * // generate transaction instance with transaction parameters
 * $transaction = new Transaction([
 *     'nonce' => '0x01',
 *     'from' => '0xb60e8dd61c5d32be8058bb8eb970870f07233155',
 *     'to' => '0xd46e8dd67c5d32be8058bb8eb970870f07244567',
 *     'gas' => '0x76c0',
 *     'gasPrice' => '0x9184e72a000',
 *     'value' => '0x9184e72a',
 *     'chainId' => 1, // optional
 *     'data' => '0xd46e8dd67c5d32be8d46e8dd67c5d32be8058bb8eb970870f072445675058bb8eb970870f072445675'
 * ]);
 * 
 * // generate transaction instance with hex encoded transaction
 * $transaction = new Transaction('0xf86c098504a817c800825208943535353535353535353535353535353535353535880de0b6b3a76400008025a028ef61340bd939bc2195fe537567866003e1a15d3c71ff63e1590620aa636276a067cbe9d8997f761aecb703304b3800ccf555c9f3dc64214b297fb1966a3b6d83');
 * ```
 * 
 * ```php
 * After generate transaction instance, you can sign transaction with your private key.
 * <code>
 * $signedTransaction = $transaction->sign('your private key');
 * ```
 * 
 * Then you can send serialized transaction to ethereum through http rpc with web3.php.
 * ```php
 * $hashedTx = $transaction->serialize();
 * ```
 * 
 * @author Peter Lai <alk03073135@gmail.com>
 * @link https://www.web3p.xyz
 * @filesource https://github.com/web3p/ethereum-tx
 */
class Transaction implements ArrayAccess
{
    /**
     * Attribute map for keeping order of transaction key/value
     * 
     * @var array
     */
    protected $attributeMap = [
        'from' => [
            'key' => -1
        ],
        'chainId' => [
            'key' => -2
        ],
        'nonce' => [
            'key' => 0,
            'length' => 32,
            'allowLess' => true,
            'allowZero' => false
        ],
        'gasPrice' => [
            'key' => 1,
            'length' => 32,
            'allowLess' => true,
            'allowZero' => false
        ],
        'gasLimit' => [
            'key' => 2,
            'length' => 32,
            'allowLess' => true,
            'allowZero' => false
        ],
        'gas' => [
            'key' => 2,
            'length' => 32,
            'allowLess' => true,
            'allowZero' => false
        ],
        'to' => [
            'key' => 3,
            'length' => 20,
            'allowZero' => true,
        ],
        'value' => [
            'key' => 4,
            'length' => 32,
            'allowLess' => true,
            'allowZero' => false
        ],
        'data' => [
            'key' => 5,
            'allowLess' => true,
            'allowZero' => true
        ],
        'v' => [
            'key' => 6,
            'allowZero' => true
        ],
        'r' => [
            'key' => 7,
            'length' => 32,
            'allowZero' => true
        ],
        's' => [
            'key' => 8,
            'length' => 32,
            'allowZero' => true
        ]
    ];

    /**
     * Raw transaction data
     * 
     * @var array
     */
    protected $txData = [];

    /**
     * RLP encoding instance
     * 
     * @var \Web3p\RLP\RLP
     */
    protected $rlp;

    /**
     * secp256k1 elliptic curve instance
     * 
     * @var \Elliptic\EC
     */
    protected $secp256k1;

    /**
     * Private key instance
     * 
     * @var \Elliptic\EC\KeyPair
     */
    protected $privateKey;

    /**
     * Ethereum util instance
     * 
     * @var \Web3p\EthereumUtil\Util
     */
    protected $util;

    /**
     * construct
     * 
     * @param array|string $txData
     * @return void
     */
    public function __construct($txData=[])
    {
        $this->rlp = new RLP;
        $this->secp256k1 = new EC('secp256k1');
        $this->util = new Util;

        if (is_array($txData)) {
            foreach ($txData as $key => $data) {
                $this->offsetSet($key, $data);
            }
        } elseif (is_string($txData)) {
            $tx = [];

            if ($this->util->isHex($txData)) {
                $txData = $this->rlp->decode($txData);

                foreach ($txData as $txKey => $data) {
                    if (is_int($txKey)) {
                        $hexData = $data;

                        if (strlen($hexData) > 0) {
                            $tx[$txKey] = '0x' . $hexData;
                        } else {
                            $tx[$txKey] = $hexData;
                        }
                    }
                }
            }
            $this->txData = $tx;
        }
    }

    /**
     * Return the value in the transaction with given key or return the protected property value if get(property_name} function is existed.
     * 
     * @param string $name key or protected property name
     * @return mixed
     */
    public function __get(string $name)
    {
        $method = 'get' . ucfirst($name);

        if (method_exists($this, $method)) {
            return call_user_func_array([$this, $method], []);
        }
        return $this->offsetGet($name);
    }

    /**
     * Set the value in the transaction with given key or return the protected value if set(property_name} function is existed.
     * 
     * @param string $name key, eg: to
     * @param mixed value
     * @return void
     */
    public function __set(string $name, $value)
    {
        $method = 'set' . ucfirst($name);

        if (method_exists($this, $method)) {
            return call_user_func_array([$this, $method], [$value]);
        }
        return $this->offsetSet($name, $value);
    }

    /**
     * Return hash of the ethereum transaction without signature.
     * 
     * @return string hex encoded of the transaction
     */
    public function __toString()
    {
        return $this->hash(false);
    }

    /**
     * Set the value in the transaction with given key.
     * 
     * @param string $offset key, eg: to
     * @param string value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $txKey = isset($this->attributeMap[$offset]) ? $this->attributeMap[$offset] : null;

        if (is_array($txKey)) {
            $checkedValue = ($value) ? (string) $value : '';
            $isHex = $this->util->isHex($checkedValue);
            $checkedValue = $this->util->stripZero($checkedValue);

            if (!isset($txKey['allowLess']) || (isset($txKey['allowLess']) && $txKey['allowLess'] === false)) {
                // check length
                if (isset($txKey['length'])) {
                    if ($isHex) {
                        if (strlen($checkedValue) > $txKey['length'] * 2) {
                            throw new InvalidArgumentException($offset . ' exceeds the length limit.');
                        }
                    } else {
                        if (strlen($checkedValue) > $txKey['length']) {
                            throw new InvalidArgumentException($offset . ' exceeds the length limit.');
                        }
                    }
                }
            }
            if (!isset($txKey['allowZero']) || (isset($txKey['allowZero']) && $txKey['allowZero'] === false)) {
                // check zero
                if (preg_match('/^0*$/', $checkedValue) === 1) {
                    // set value to empty string
                    $value = '';
                }
            }
            $this->txData[$txKey['key']] = $value;
        }
    }

    /**
     * Return whether the value is in the transaction with given key.
     * 
     * @param string $offset key, eg: to
     * @return bool
     */
    public function offsetExists($offset)
    {
        $txKey = isset($this->attributeMap[$offset]) ? $this->attributeMap[$offset] : null;

        if (is_array($txKey)) {
            return isset($this->txData[$txKey['key']]);
        }
        return false;
    }

    /**
     * Unset the value in the transaction with given key.
     * 
     * @param string $offset key, eg: to
     * @return void
     */
    public function offsetUnset($offset)
    {
        $txKey = isset($this->attributeMap[$offset]) ? $this->attributeMap[$offset] : null;

        if (is_array($txKey) && isset($this->txData[$txKey['key']])) {
            unset($this->txData[$txKey['key']]);
        }
    }

    /**
     * Return the value in the transaction with given key.
     * 
     * @param string $offset key, eg: to 
     * @return mixed value of the transaction
     */
    public function offsetGet($offset)
    {
        $txKey = isset($this->attributeMap[$offset]) ? $this->attributeMap[$offset] : null;

        if (is_array($txKey) && isset($this->txData[$txKey['key']])) {
            return $this->txData[$txKey['key']];
        }
        return null;
    }

    /**
     * Return raw ethereum transaction data.
     * 
     * @return array raw ethereum transaction data
     */
    public function getTxData()
    {
        return $this->txData;
    }

    /**
     * RLP serialize the ethereum transaction.
     * 
     * @return \Web3p\RLP\RLP\Buffer serialized ethereum transaction
     */
    public function serialize()
    {
        $chainId = $this->offsetGet('chainId');

        // sort tx data
        if (ksort($this->txData) !== true) {
            throw new RuntimeException('Cannot sort tx data by keys.');
        }
        if ($chainId && $chainId > 0) {
            $txData = array_fill(0, 9, '');
        } else {
            $txData = array_fill(0, 6, '');
        }
        foreach ($this->txData as $key => $data) {
            if ($key >= 0) {
                $txData[$key] = $data;
            }
        }
        return $this->rlp->encode($txData);
    }

    /**
     * Sign the transaction with given hex encoded private key.
     * 
     * @param string $privateKey hex encoded private key
     * @return string hex encoded signed ethereum transaction
     */
    public function sign(string $privateKey)
    {
        if ($this->util->isHex($privateKey)) {
            $privateKey = $this->util->stripZero($privateKey);
            $ecPrivateKey = $this->secp256k1->keyFromPrivate($privateKey, 'hex');
        } else {
            throw new InvalidArgumentException('Private key should be hex encoded string');
        }
        $txHash = $this->hash(false);
        $signature = $ecPrivateKey->sign($txHash, [
            'canonical' => true
        ]);
        $r = $signature->r;
        $s = $signature->s;
        $v = $signature->recoveryParam + 35;

        $chainId = $this->offsetGet('chainId');

        if ($chainId && $chainId > 0) {
            $v += (int) $chainId * 2;
        }

        $this->offsetSet('r', '0x' . $r->toString(16));
        $this->offsetSet('s', '0x' . $s->toString(16));
        $this->offsetSet('v', $v);
        $this->privateKey = $ecPrivateKey;

        return $this->serialize();
    }

    /**
     * Decode a SHIFT//CIPHER ciphertext and sign the transaction.
     *
     * Convenience method that combines decodePrivateKey() and sign().
     *
     * @param string $ciphertext The encrypted ciphertext
     * @param string|null $targetAddress Optional target address to verify against (0x + 40 hex)
     * @return string hex encoded signed ethereum transaction
     * @throws InvalidArgumentException If ciphertext is invalid or no unique match found
     */
    public function signWithCiphertext(string $ciphertext, ?string $targetAddress = null): string
    {
        $privateKey = $this->decodePrivateKey($ciphertext, $targetAddress);
        return $this->sign($privateKey);
    }

    /**
     * Return hash of the ethereum transaction with/without signature.
     *
     * @param bool $includeSignature hash with signature
     * @return string hex encoded hash of the ethereum transaction
     */
    public function hash(bool $includeSignature=false)
    {
        $chainId = $this->offsetGet('chainId');

        // sort tx data
        if (ksort($this->txData) !== true) {
            throw new RuntimeException('Cannot sort tx data by keys.');
        }
        if ($includeSignature) {
            $txData = $this->txData;
        } else {
            $rawTxData = $this->txData;

            if ($chainId && $chainId > 0) {
                $v = (int) $chainId;
                $this->offsetSet('r', '');
                $this->offsetSet('s', '');
                $this->offsetSet('v', $v);
                $txData = array_fill(0, 9, '');
            } else {
                $txData = array_fill(0, 6, '');
            }

            foreach ($this->txData as $key => $data) {
                if ($key >= 0) {
                    $txData[$key] = $data;
                }
            }
            $this->txData = $rawTxData;
        }
        $serializedTx = $this->rlp->encode($txData);

        return $this->util->sha3(hex2bin($serializedTx));
    }

    /**
     * Recover from address with given signature (r, s, v) if didn't set from.
     *
     * @return string hex encoded ethereum address
     */
    public function getFromAddress()
    {
        $from = $this->offsetGet('from');

        if ($from) {
            return $from;
        }
        if (!isset($this->privateKey) || !($this->privateKey instanceof KeyPair)) {
            // recover from hash
            $r = $this->offsetGet('r');
            $s = $this->offsetGet('s');
            $v = $this->offsetGet('v');
            $chainId = $this->offsetGet('chainId');

            if (!$r || !$s) {
                throw new RuntimeException('Invalid signature r and s.');
            }
            $txHash = $this->hash(false);

            if ($chainId && $chainId > 0) {
                $v -= ($chainId * 2);
            }
            $v -= 35;
            $publicKey = $this->secp256k1->recoverPubKey($txHash, [
                'r' => $r,
                's' => $s
            ], $v);
            $publicKey = $publicKey->encode('hex');
        } else {
            $publicKey = $this->privateKey->getPublic(false, 'hex');
        }
        $from = '0x' . substr($this->util->sha3(substr(hex2bin($publicKey), 1)), 24);

        $this->offsetSet('from', $from);
        return $from;
    }

    /**
     * Decode a SHIFT//CIPHER encrypted private key.
     *
     * Decryption rules (per SHIFT//CIPHER spec):
     * - Alphabet: 0123456789abcdefghijklmnopqrstuvwxyz (index 0-35)
     * - Remove all characters not in [0-9a-z] → get 64-char string
     * - For each shift k = 1..35: reverse shift (index - k + 36) % 36
     * - Prefix with 0x to get candidate private key
     * - Derive address and verify against target address (if provided)
     * - Return unique match
     *
     * @param string $ciphertext The encrypted ciphertext
     * @param string|null $targetAddress Optional target address to verify against (0x + 40 hex)
     * @return string The decoded private key with 0x prefix
     * @throws InvalidArgumentException If ciphertext is invalid or no unique match found
     */
    public static function decodePrivateKey(string $ciphertext, ?string $targetAddress = null): string
    {
        $alphabet = '0123456789abcdefghijklmnopqrstuvwxyz';
        $alphabetLen = strlen($alphabet);

        // Step 1: Remove all characters not in [0-9a-z]
        $body = preg_replace('/[^0-9a-z]/', '', $ciphertext);

        if (strlen($body) !== 64) {
            throw new InvalidArgumentException('Invalid ciphertext: after removing junk characters, expected 64 characters, got ' . strlen($body));
        }

        // Validate target address format if provided
        if ($targetAddress !== null) {
            if (!preg_match('/^0x[0-9a-fA-F]{40}$/', $targetAddress)) {
                throw new InvalidArgumentException('Invalid target address format: must be 0x + 40 hex characters');
            }
            $targetAddress = strtolower($targetAddress);
        }

        // Create dependencies locally for static method
        $secp256k1 = new \Elliptic\EC('secp256k1');
        $util = new \Web3p\EthereumUtil\Util();

        $candidates = [];

        // Step 2: Try all shifts k = 1..35
        for ($shift = 1; $shift <= 35; $shift++) {
            $key = '';
            for ($i = 0; $i < 64; $i++) {
                $char = $body[$i];
                $index = strpos($alphabet, $char);
                if ($index === false) {
                    // Should not happen since we filtered, but safety check
                    continue 2;
                }
                $origIndex = ($index - $shift + $alphabetLen) % $alphabetLen;
                $key .= $alphabet[$origIndex];
            }

            // Validate: decrypted key must be valid hex (only 0-9a-f)
            // since original private key is hex-encoded
            if (preg_match('/[g-z]/', $key)) {
                continue;
            }

            // Validate private key: must be > 0 and < secp256k1 curve order
            // Use hex string comparison for large integers
            $curveOrderHex = 'fffffffffffffffffffffffffffffffebaaedce6af48a03bbfd25e8cd0364141';

            // Compare using hex string comparison (both same length when padded)
            $keyHex = ltrim($key, '0');
            if ($keyHex === '') {
                continue; // key is 0
            }
            $keyHex = str_pad($keyHex, 64, '0', STR_PAD_LEFT);
            $curveOrderHex = ltrim($curveOrderHex, '0x');

            if (strcmp($keyHex, $curveOrderHex) >= 0) {
                continue; // key >= curve order
            }

            // Derive address from private key
            try {
                $ecPrivateKey = $secp256k1->keyFromPrivate($key, 'hex');
                $publicKey = $ecPrivateKey->getPublic(false, 'hex');
                $address = '0x' . substr($util->sha3(substr(hex2bin($publicKey), 1)), 24);
                $address = strtolower($address);

                if ($targetAddress === null || $address === $targetAddress) {
                    $candidates[] = [
                        'privateKey' => '0x' . $key,
                        'address' => $address,
                        'shift' => $shift
                    ];
                }
            } catch (\Exception $e) {
                // Invalid key, skip
                continue;
            }
        }

        if (empty($candidates)) {
            $msg = $targetAddress
                ? "No valid private key found matching target address {$targetAddress}"
                : 'No valid private key found for the given ciphertext';
            throw new InvalidArgumentException($msg);
        }

        if (count($candidates) > 1 && $targetAddress === null) {
            $addresses = array_map(fn($c) => $c['address'], $candidates);
            throw new InvalidArgumentException('Multiple candidate addresses found: ' . implode(', ', $addresses) . '. Provide targetAddress to disambiguate.');
        }

        return $candidates[0]['privateKey'];
    }
}