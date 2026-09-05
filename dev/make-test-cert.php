<?php
declare(strict_types=1);

// 生成测试用自签证书（仅开发测试）
$dir = $argv[1] ?? __DIR__ . '/../var/data/caddy/certificates/acme/demo.test';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}
$cnf = getenv('OPENSSL_CONF') ?: (is_file('C:/work/xampp/php/extras/openssl/openssl.cnf') ? 'C:/work/xampp/php/extras/openssl/openssl.cnf' : '');
$opts = $cnf !== '' ? ['config' => $cnf] : [];
$dn = ['commonName' => 'demo.test'];
$pkey = openssl_pkey_new(array_merge($opts, ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]));
$csr = openssl_csr_new($dn, $pkey, array_merge($opts, ['digest_alg' => 'sha256']));
$cert = openssl_csr_sign($csr, null, $pkey, 365, array_merge($opts, ['digest_alg' => 'sha256']), random_int(1, 100000));
openssl_x509_export($cert, $certOut);
openssl_pkey_export($pkey, $keyOut);
file_put_contents($dir . '/demo.test.crt', $certOut);
file_put_contents($dir . '/demo.test.key', $keyOut);
echo "OK: {$dir}\n";
