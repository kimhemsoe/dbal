<?php

namespace Doctrine\Tests\DBAL\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\Tests\DBAL\Mocks\MockPlatform;

class TimeTest extends \Doctrine\Tests\DbalTestCase
{
    /**
     * @var MockPlatform
     */
    private $_platform;

    /**
     * @var \Doctrine\DBAL\Types\TimeType
     */
    private $_type;

    protected function setUp()
    {
        $this->_platform = new MockPlatform();
        $this->_type = Type::getType('time');
    }

    public function testTimeConvertsToDatabaseValue()
    {
        $this->assertTrue(
            is_string($this->_type->convertToDatabaseValue(new \DateTime(), $this->_platform))
        );
    }

    /**
     * @dataProvider invalidPHPValuesProvider
     * @param mixed $value
     */
    public function testInvalidTypeConversionToDatabaseValue($value)
    {
        $this->setExpectedException('Doctrine\DBAL\Types\ConversionException');

        $this->_type->convertToDatabaseValue($value, $this->_platform);
    }

    public function testTimeConvertsToPHPValue()
    {
        $this->assertTrue(
            $this->_type->convertToPHPValue('5:30:55', $this->_platform)
            instanceof \DateTime
        );
    }

    public function testDateFieldResetInPHPValue()
    {
        $time = $this->_type->convertToPHPValue('01:23:34', $this->_platform);
        $this->assertEquals('01:23:34', $time->format('H:i:s'));
        $this->assertEquals('1970-01-01', $time->format('Y-m-d'));
    }

    public function testInvalidTimeFormatConversion()
    {
        $this->setExpectedException('Doctrine\DBAL\Types\ConversionException');
        $this->_type->convertToPHPValue('abcdefg', $this->_platform);
    }

    public function testNullConversion()
    {
        $this->assertNull($this->_type->convertToPHPValue(null, $this->_platform));
    }

    public function testConvertDateTimeToPHPValue()
    {
        $date = new \DateTime("now");
        $this->assertSame($date, $this->_type->convertToPHPValue($date, $this->_platform));
    }

    /**
     * @return mixed[][]
     */
    public function invalidPHPValuesProvider()
    {
        return [
            [0],
            [''],
            ['foo'],
            ['10:11:12'],
            [new \stdClass()],
            [$this],
            [27],
            [-1],
            [1.2],
            [[]],
            [['an array']],
        ];
    }
}
