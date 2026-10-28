<?php

namespace App\Enums;

enum MovementType: string
{
    case Receipt = 'receipt';
    case Sale = 'sale';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case TransferReturn = 'transfer_return';
    case Adjustment = 'adjustment';
    case Return = 'return';
}
