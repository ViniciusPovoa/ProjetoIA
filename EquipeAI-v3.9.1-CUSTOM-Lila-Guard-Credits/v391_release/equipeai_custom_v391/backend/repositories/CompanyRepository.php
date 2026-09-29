<?php
namespace App\repositories;
use App\config\Database;
final class CompanyRepository {
    public function find(int $id):?array{$s=Database::get()->prepare('SELECT * FROM companies WHERE id=?');$s->execute([$id]);return $s->fetch()?:null;}
    public function services(int $companyId):array{$s=Database::get()->prepare('SELECT * FROM company_services WHERE company_id=? ORDER BY active DESC,id DESC');$s->execute([$companyId]);return $s->fetchAll();}
}
