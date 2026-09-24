<?php
/**
 * config/auth.php
 * Senha fixa de acesso ao módulo Caixa.
 *
 * Isso é uma proteção BÁSICA (senha única e fixa, sem usuários
 * individuais). Serve para impedir que qualquer pessoa que abra o
 * site chegue direto nas telas de cadastro/recarga/relatório.
 *
 * Quando o projeto evoluir, o ideal é trocar isso por login de
 * usuário com senha própria no banco (hash com password_hash()).
 *
 * TROQUE a senha abaixo antes do evento.
 */

define('SENHA_CAIXA', 'guia2026');
