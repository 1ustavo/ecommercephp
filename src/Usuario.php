<?php

class Usuario
{
    public int $id;
    public string $nome;
    public string $email;
    private string $password;
    private bool $ativo;
    public string $cargo;
    public string $updated_at;
    public string $created_at;
}
