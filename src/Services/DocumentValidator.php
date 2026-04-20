<?php
declare(strict_types=1);

namespace Art\SelecaoNextSi\Services;

class DocumentValidator
{
    /**
     * Normaliza o documento, mantendo estritamente apenas números.
     */
    public static function sanitize(string $document): string
    {
        return preg_replace('/[^0-9]/', '', $document);
    }

    /**
     * Valida CPF ou CNPJ verificando os dígitos verificadores matematicamente.
     */
    public static function isValid(string $document): bool
    {
        $cleanDocument = self::sanitize($document);

        if (strlen($cleanDocument) === 11) {
            return self::validateCPF($cleanDocument);
        }

        if (strlen($cleanDocument) === 14) {
            return self::validateCNPJ($cleanDocument);
        }

        return false;
    }

    private static function validateCPF(string $cpf): bool
    {
        // Verifica se todos os dígitos são iguais (ex: 111.111.111-11)
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        // Cálculo dos dígitos verificadores (primeiro e segundo)
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += (int)$cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            
            if ((int)$cpf[$c] !== $d) {
                return false;
            }
        }
        return true;
    }

    private static function validateCNPJ(string $cnpj): bool
    {
        // Verifica se todos os dígitos são iguais
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        // Validação do primeiro dígito
        for ($i = 0, $j = 5, $soma = 0; $i < 12; $i++) {
            $soma += (int)$cnpj[$i] * $j;
            $j = ($j === 2) ? 9 : $j - 1;
        }
        $resto = $soma % 11;
        $digito1 = $resto < 2 ? 0 : 11 - $resto;
        
        if ((int)$cnpj[12] !== $digito1) {
            return false;
        }

        // Validação do segundo dígito
        for ($i = 0, $j = 6, $soma = 0; $i < 13; $i++) {
            $soma += (int)$cnpj[$i] * $j;
            $j = ($j === 2) ? 9 : $j - 1;
        }
        $resto = $soma % 11;
        $digito2 = $resto < 2 ? 0 : 11 - $resto;
        
        if ((int)$cnpj[13] !== $digito2) {
            return false;
        }

        return true;
    }
}