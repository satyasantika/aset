<?php

namespace App\Enums;

enum PenyediaBerkas: string
{
    case GoogleDrive = 'google_drive';
    case GoogleDocs = 'google_docs';
    case Onedrive = 'onedrive';
    case Unsil = 'unsil';
    case Lainnya = 'lainnya';
}
