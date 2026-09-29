<?php
namespace App\Domain\Auth\Enums;
enum HouseholdRole:string { case Owner='owner'; case FamilyMember='family_member'; case Viewer='viewer'; }
