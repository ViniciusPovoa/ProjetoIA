<?php
namespace App\contracts;
interface AgentInterface {
    public function validateInput(array $input,array $schema): array;
    public function buildUserContent(array $input): string;
}
