<?php

namespace App\Enums;

enum PauseResumeAction: string
{
    use GenericMethods;

    case Pause = 'hold';
    case Resume = 'release';
}
