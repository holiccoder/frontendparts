<?php

namespace App\Enums;

enum ComponentEventType: string
{
    case View = 'view';
    case Copy = 'copy';
    case Download = 'download';
    case Scaffold = 'scaffold';
    // Blur-gate hit: a non-entitled user opened a paid component's modal
    // payload (SPEC §5.4 blur-gate; feeds the B2 upgrade trigger, §16.2).
    case GateHit = 'gate_hit';
}
