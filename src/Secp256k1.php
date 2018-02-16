<?php
/**
 * This file is part of secp256k1 package.
 * 
 * (c) Kuan-Cheng,Lai <alk03073135@gmail.com>
 * 
 * @author Peter Lai <alk03073135@gmail.com>
 * @license MIT
 */

namespace Secp256k1;

use InvalidArgumentException;
use Mdanter\Ecc\EccFactory;
use Mdanter\Ecc\Curves\CurveFactory;
use Mdanter\Ecc\Curves\SecgCurve;
use Mdanter\Ecc\Crypto\Signature\SignatureInterface;
use Secp256k1\Serializer\HexPrivateKeySerializer;
use Secp256k1\Serializer\HexSignatureSerializer;
use Secp256k1\Signature\Signer;

class Secp256k1
{
    /**
     * adapter
     *
     * @var \Mdanter\Ecc\Math\GmpMathInterface
     */
    protected $adapter;

    /**
     * generator
     *
     * @var \Mdanter\Ecc\Primitives\Point
     */
    protected $generator;

    /**
     * deserializer
     *
     * @var \Secp256k1\Serializer\HexPrivateKeySerializer
     */
    protected $deserializer;

    /**
     * algorithm
     * 
     * @var string
     */
    protected $algorithm;

    /**
     * construct
     *
     * @param string $hashAlgorithm
     * @return void
     */
    public function __construct($hashAlgorithm='sha256')
    {
        $this->adapter = EccFactory::getAdapter();
        $this->generator = CurveFactory::getGeneratorByName(SecgCurve::NAME_SECP_256K1);
        $this->deserializer = new HexPrivateKeySerializer($this->generator);
        $this->algorithm = $hashAlgorithm;
    }

    /**
     * get
     * 
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        $method = 'get' . ucfirst($name);

        if (method_exists($this, $method)) {
            return call_user_func_array([$this, $method], []);
        }
        return false;
    }

    /**
     * set
     * 
     * @param string $name
     * @param mixed $value
     * @return bool
     */
    public function __set($name, $value)
    {
        $method = 'set' . ucfirst($name);

        if (method_exists($this, $method)) {
            return call_user_func_array([$this, $method], [$value]);
        }
        return false;
    }

    /**
     * getDeserializer
     * 
     * @return \Secp256k1\Serializer\HexPrivateKeySerializer
     */
    // public function getDeserializer()
    // {
    //     return $this->deserializer;
    // }

    /**
     * sign
     * 
     * @param string $hash
     * @param string $privateKey
     * @param array $options
     * @return \Mdanter\Ecc\Crypto\Signature\SignatureInterface
     */
    public function sign(string $hash, string $privateKey, array $options=[]): SignatureInterface
    {
        $key = $this->deserializer->parse($privateKey);
        $hash = gmp_init($hash, 16);

        // if (!isset(options['n'])) {
        //     $random = \Mdanter\Ecc\Random\RandomGeneratorFactory::getHmacRandomGenerator($key, $hash, $this->algorithm);
        //     $n = $this->generator->getOrder();
        //     $randomK = $random->generate($n);

        //     $options['n']  = $n;
        //     $options['canonical'] = true;
        // }
        // if (!isset(options['canonical'])) {
        //     $options['canonical'] = false;
        // }s
        $random = \Mdanter\Ecc\Random\RandomGeneratorFactory::getHmacRandomGenerator($key, $hash, $this->algorithm);
        $n = $this->generator->getOrder();
        $randomK = $random->generate($n);
        $options['n']  = $n;
        $options['canonical'] = true;
        $signer = new Signer($this->adapter, $options);

        return $signer->sign($key, $hash, $randomK);
    }

    /**
     * verify
     * 
     * @param string $hash
     * @param \Mdanter\Ecc\Crypto\Signature\SignatureInterface $signature
     * @param string $publicKey
     * @return bool
     */
    public function verify(string $hash, SignatureInterface $signature, string $publicKey): bool
    {
        $gmpKey = $this->decodePoint($publicKey);
        $key = $this->generator->getPublickeyFrom($gmpKey[0], $gmpKey[1]);
        $hash = gmp_init($hash, 16);
        $signer = new Signer($this->adapter);

        return $signer->verify($key, $signature, $hash);
    }

    /**
     * decodePoint
     * 
     * @param string $publicKey
     * @return array
     */
    protected function decodePoint(string $publicKey): array
    {
        $order = gmp_strval($this->generator->getOrder(), 16);
        $length = mb_strlen($order);
        $keyLength = mb_strlen($publicKey);
        $num = hexdec(mb_substr($publicKey, 0, 2));

        if (
            ($num === 4 || $num === 6 || $num === 7) &&
            ($length * 2 + 2) === $keyLength
            ) {
            $x = gmp_init(mb_substr($publicKey, 2, $length), 16);
            $y = gmp_init(mb_substr($publicKey, ($length + 2), $length), 16);

            if ($this->generator->isValid($x, $y) !== true) {
                throw new InvalidArgumentException('Invalid public key point x and y.');
            }

            $res = [
                $x, $y
            ];
            return $res;
        } elseif (
            ($num === 2 || $num === 3) &&
            ($length + 2) === $keyLength
        ) {
            $x = gmp_init(mb_substr($publicKey, 2, $length), 16);
            $y = $this->generator->getCurve()->recoverYfromX($num === 3, $x);
            $res = [
                $x, $y
            ];
            return $res;
        }
        throw new InvalidArgumentException('Invalid public key point format.');
    }
}