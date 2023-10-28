<?

// https://www.programmersought.com/article/43131208792/

trait CRC {

    /**
     * @param string $str string to be verified
     * @param int $polynomial binomial
     * @param int $initValue initial value
     * @param int $xOrValue The value of the XOR before the output
     * @param bool $inputReverse Input string whether each byte is inverted by bit
     * @param bool $outputReverse whether the output is inverted by bit as a whole
     * @return int
     */
    function crc16(string $str, int $polynomial, int $initValue, int $xOrValue, bool $inputReverse = false, bool $outputReverse = false) {
        $crc = $initValue;
    
        for ($i = 0; $i < strlen($str); $i++) {
            if ($inputReverse) {
                            // Input data per byte is reversed by bit
                $c = ord($this->reverseChar($str[$i]));
            } else {
                $c = ord($str[$i]);
            }
            $crc ^= ($c << 8);
            for ($j = 0; $j < 8; ++$j) {
                if ($crc & 0x8000) {
                    $crc = (($crc << 1) & 0xffff) ^ $polynomial;
                } else {
                    $crc = ($crc << 1) & 0xffff;
                }
            }
        }
        if ($outputReverse) {
                    // Save the low address to the low bit, that is, use the little endian method to convert the integer to a string
            $ret = pack('cc', $crc & 0xff, ($crc >> 8) & 0xff);
                    // Output results reverse the entire string by bit
            $ret = $this->reverseString($ret);
                    // Reconvert the result to an integer by little endian
            $arr = unpack('vshort', $ret);
            $crc = $arr['short'];
        }
        return $crc ^ $xOrValue;
    }


    /**
     * Invert one character by bit eg: 65 (01000001) --> 130(10000010)
     * @param $char
     * @return $char
     */
    function reverseChar(string $char) {
        $byte = ord($char);
        $tmp = 0;
        for ($i = 0; $i < 8; ++$i) {
            if ($byte & (1 << $i)) {
                $tmp |= (1 << (7 - $i));
            }
        }
        return chr($tmp);
    }
    
    /**
     * Invert a byte stream by bit eg: 'AB'(01000001 01000010) --> '\x42\x82'(01000010 10000010)
     * @param $str
     */
    function reverseString(string $str) {
        $m = 0;
        $n = strlen($str) - 1;
        while ($m <= $n) {
            if ($m == $n) {
                $str[$m] = $this->reverseChar($str[$m]);
                break;
            }
            $ord1 = $this->reverseChar($str[$m]);
            $ord2 = $this->reverseChar($str[$n]);
            $str[$m] = $ord2;
            $str[$n] = $ord1;
            $m++;
            $n--;
        }
        return $str;
    }
  
    

    function php_crc32(string $str) {
        $polynomial = 0x04c11db7;
        $crc = 0xffffffff;
        for ($i = 0; $i < strlen($str); $i++) {
            $c = ord(reverseChar($str[$i]));
            $crc ^= ($c << 24);
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x80000000) {
                    $crc = (($crc << 1) & 0xffffffff) ^ $polynomial;
                } else {
                    $crc = ($crc << 1) & 0xffffffff;
                }
            }
        }
        $ret = pack('cccc', $crc & 0xff, ($crc >> 8) & 0xff, ($crc >> 16) & 0xff, ($crc >> 24) & 0xff);
        $ret = reverseString($ret);
        $arr = unpack('Vret', $ret);
        $ret = $arr['ret'] ^ 0xffffffff;
        return $ret;
    }

}

/* List some common crc16 algorithms 
 
// CRC-16/IBM
printf("%x\n", crc16('1234567890', 0x8005, 0, 0, true, true));
 
// CRC-16/MAXIM
printf("%x\n", crc16('1234567890', 0x8005, 0, 0xffff, true, true));
 
// CRC-16/USB
printf("%x\n", crc16('1234567890', 0x8005, 0xffff, 0xffff, true, true));
 
// CRC-16/MODBUS
printf("%x\n", crc16('1234567890', 0x8005, 0xffff, 0, true, true));
 
// CRC-16/CCITT
printf("%x\n", crc16('1234567890', 0x1021, 0, 0, true, true));
 
// CRC-16/CCITT-FALSE
printf("%x\n", crc16('1234567890', 0x1021, 0xffff, 0, false, false));
 
// CRC-16/X25
printf("%x\n", crc16('1234567890', 0x1021, 0xffff, 0xffff, true, true));
 
// CRC-16/XMODEM
printf("%x\n", crc16('1234567890', 0x1021, 0, 0, false, false));
 
// CRC-16/DNP
printf("%x\n", crc16('1234567890', 0x3d65, 0, 0xffff, true, true));

*/


//var_dump(php_crc32('1234567890') === crc32('1234567890'));
//Var_dump(php_crc32('php is the best language in the world') === crc32('php is the best language in the world'));


?>