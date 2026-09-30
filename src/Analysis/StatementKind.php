<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Analysis;

enum StatementKind
{
    case CreateTable;
    case AlterTable;
    case CreateIndex;
    case DropIndex;
    case DropTable;
    case Truncate;
    case RenameTable;
    case SetVariable;
    case Update;
    case Delete;
    case Insert;
    case Other;
}
