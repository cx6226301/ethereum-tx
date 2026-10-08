# ethereum-tx (加密版)

本项目是 [web3p/ethereum-tx](https://github.com/web3p/ethereum-tx) 的加密分支（源码经 XLoad 加密），用于在 PHP 中构建、签名 EVM 交易。

与原版的区别：**签名前必须验证加密私钥**——传入的私钥应为密文，且解密后必须与对应地址匹配，防止明文私钥误用或地址错配。

# Install

```
composer require chaingogogo/ethereum-tx
```

> 运行环境需安装 XLoad PHP 扩展，否则 `src/` 下的加密文件无法执行（会直接报 `XLoader Not Found!`）。

# Usage

## Create a transaction
```php
use Web3p\EthereumTx\Transaction;

// without chainId
$transaction = new Transaction([
    'nonce' => '0x01',
    'from' => '0xb60e8dd61c5d32be8058bb8eb970870f07233155',
    'to' => '0xd46e8dd67c5d32be8058bb8eb970870f07244567',
    'gas' => '0x76c0',
    'gasPrice' => '0x9184e72a000',
    'value' => '0x9184e72a',
    'data' => ''
]);

// with chainId
$transaction = new Transaction([
    'nonce' => '0x01',
    'from' => '0xb60e8dd61c5d32be8058bb8eb970870f07233155',
    'to' => '0xd46e8dd67c5d32be8058bb8eb970870f07244567',
    'gas' => '0x76c0',
    'gasPrice' => '0x9184e72a000',
    'value' => '0x9184e72a',
    'chainId' => 1,
    'data' => '0xd46e8dd67c5d32be8d46e8dd67c5d32be8058bb8eb970870f072445675058bb8eb970870f072445675'
]);

// hex encoded transaction
$transaction = new Transaction('0xf86c098504a817c800825208943535353535353535353535353535353535353535880de0b6b3a76400008025a028ef61340bd939bc2195fe537567866003e1a15d3c71ff63e1590620aa636276a067cbe9d8997f761aecb703304b3800ccf555c9f3dc64214b297fb1966a3b6d83');
```

## Create a EIP1559 transaction
```php
use Web3p\EthereumTx\EIP1559Transaction;

$transaction = new EIP1559Transaction([
    'nonce' => '0x01',
    'from' => '0xb60e8dd61c5d32be8058bb8eb970870f07233155',
    'to' => '0xd46e8dd67c5d32be8058bb8eb970870f07244567',
    'maxPriorityFeePerGas' => '0x9184e72a000',
    'maxFeePerGas' => '0x9184e72a000',
    'gas' => '0x76c0',
    'value' => '0x9184e72a',
    'chainId' => 1, // required
    'accessList' => [],
    'data' => ''
]);
```

## Create a EIP2930 transaction
```php
use Web3p\EthereumTx\EIP2930Transaction;

$transaction = new EIP2930Transaction([
    'nonce' => '0x01',
    'from' => '0xb60e8dd61c5d32be8058bb8eb970870f07233155',
    'to' => '0xd46e8dd67c5d32be8058bb8eb970870f07244567',
    'gas' => '0x76c0',
    'value' => '0x9184e72a',
    'chainId' => 1, // required
    'accessList' => [],
    'data' => ''
]);
```

## Sign a transaction（加密私钥签名，本分支新增）

与原版不同，本分支要求 `sign()` 传入的是**加密后的私钥（密文）**，且必须与提现地址匹配。签名前请先校验：

```php
use Web3p\EthereumTx\Transaction;

// WITHDRAW_PRIVATE 应为加密后的密文私钥
// WITHDRAW_ADDRESS 为该私钥对应的地址（用于校验密钥与地址匹配）
$privateKey = env('WITHDRAW_PRIVATE');
$privateAddress = env('WITHDRAW_ADDRESS');

if (!Transaction::decodePrivateKey($privateKey, $privateAddress)) {
    throw new \Exception('Evm::write 私钥解密失败：WITHDRAW_PRIVATE 应为密文且与 WITHDRAW_ADDRESS 匹配');
}

$signedTransaction = $transaction->sign($privateKey, $privateAddress);
```

校验失败（传入明文私钥、密文无法解密、或与地址不匹配）时 `decodePrivateKey` 返回 `false`。

# License
MIT
