<?php
namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
class SiretExists extends Constraint
{
    public string $message = 'Le SIRET "{{ siret }}" est introuvable dans la base Sirene.';
}
