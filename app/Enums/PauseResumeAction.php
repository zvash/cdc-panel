<?php

namespace App\Enums;

enum PauseResumeAction: string
{
    use GenericMethods;

    case Pause = 'pause';
    case Resume = 'resume';
}
