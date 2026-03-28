<?php

namespace App\Enums;

enum TeamMemberRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
}
