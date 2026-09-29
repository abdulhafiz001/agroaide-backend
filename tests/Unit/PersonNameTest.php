<?php

namespace Tests\Unit;

use App\Support\PersonName;
use PHPUnit\Framework\TestCase;

class PersonNameTest extends TestCase
{
    public function test_accepts_first_and_last_names(): void
    {
        $this->assertTrue(PersonName::isValid('Adaeze Okonkwo'));
        $this->assertTrue(PersonName::isValid('Mary-Jane Okafor'));
        $this->assertTrue(PersonName::isValid("O'Brien Musa"));
        $this->assertTrue(PersonName::isValid('A. Ibrahim'));
        $this->assertTrue(PersonName::isValid('  Chioma   Adaeze  '));
    }

    public function test_rejects_single_word_or_junk(): void
    {
        $this->assertFalse(PersonName::isValid('John'));
        $this->assertFalse(PersonName::isValid('asdf'));
        $this->assertFalse(PersonName::isValid('123 456'));
        $this->assertFalse(PersonName::isValid('test@farm.com'));
        $this->assertFalse(PersonName::isValid('A. B.'));
    }

    public function test_normalizes_extra_spaces(): void
    {
        $this->assertSame('Adaeze Okonkwo', PersonName::normalize('  Adaeze   Okonkwo '));
    }
}
