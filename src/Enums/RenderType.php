<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Enums;

enum RenderType: string
{
    case HELP = 'help';
    case LIST = 'list';
    case NOT_FOUND = 'not-found';
    case SUCCESS = 'success';
    case ERROR = 'error';
    case EMPTY = 'empty';
    case CONFLICT = 'conflict';
    case TABLE = 'table';
    case VALIDATION_ERROR = 'validation-error';
    case DISPLAY_MESSAGE = 'display-message';

    public function getDefaultMessage(): string
    {
        return match ($this) {
            self::SUCCESS => 'Directive executed successfully',
            self::ERROR => 'Directive execution failed',
            default => '',
        };
    }

    public function render(array $replacements = []): string
    {
        $content = $this->getContent();

        foreach ($replacements as $placeholder => $value) {
            $content = str_replace($placeholder, $value, $content);
        }

        return $content;
    }

    private function getContent(): string
    {
        return match ($this) {
            self::HELP => $this->getHelpContent(),
            self::LIST => $this->getListContent(),
            self::NOT_FOUND => $this->getNotFoundContent(),
            self::SUCCESS => $this->getSuccessContent(),
            self::ERROR => $this->getErrorContent(),
            self::EMPTY => $this->getEmptyContent(),
            self::CONFLICT => $this->getConflictContent(),
            self::TABLE => $this->getTableContent(),
            self::VALIDATION_ERROR => $this->getValidationErrorContent(),
            self::DISPLAY_MESSAGE => $this->getDisplayMessageContent(),
        };
    }

    private function getHelpContent(): string
    {
        return <<<HELP

\033[36m═══════════════════════════════════════════════════════════════════════════\033[0m
\033[1;33m🎯 Directive System - Command Line Interface\033[0m
\033[36m═══════════════════════════════════════════════════════════════════════════\033[0m

\033[1;32mUSAGE:\033[0m
  ./vendor/bin/directive <signature> [arguments] [options]

\033[1;32mCOMMANDS:\033[0m
  \033[33m--list, -l\033[0m      List all available directives
  \033[33m--help, -h\033[0m      Show this help message

\033[1;32mEXAMPLES:\033[0m
  \033[36m# Run a simple directive\033[0m
  ./vendor/bin/directive hello

  \033[36m# Run with arguments\033[0m
  ./vendor/bin/directive user-create John Doe --role=admin

  \033[36m# Run with flags\033[0m
  ./vendor/bin/directive cache-clear --force

  \033[36m# List all directives\033[0m
  ./vendor/bin/directive --list

\033[1;32mCREATE YOUR OWN DIRECTIVE:\033[0m
  1. Create a file in \033[33mapp/Directives/\033[0m
  2. Extend \033[33mAbstractDirective\033[0m
  3. Implement \033[33mgetSignature()\033[0m, \033[33mgetDescription()\033[0m and \033[33mexecute()\033[0m

\033[36m═══════════════════════════════════════════════════════════════════════════\033[0m

HELP;
    }

    private function getListContent(): string
    {
        return <<<LIST

\033[36m═══════════════════════════════════════════════════════════════════════════\033[0m
\033[1;32m✅ Available Directives ({{count}})\033[0m
\033[36m═══════════════════════════════════════════════════════════════════════════\033[0m
{{rows}}
\033[36m═══════════════════════════════════════════════════════════════════════════\033[0m

\033[90m💡 Usage: ./vendor/bin/directive <signature> [arguments] [--options]\033[0m
\033[90m📚 Run './vendor/bin/directive --help' for more information\033[0m

LIST;
    }

    private function getNotFoundContent(): string
    {
        return <<<NOTFOUND

\033[31m✗ Directive '\033[1;33m{{signature}}\033[0m\033[31m' not found\033[0m

\033[90m💡 Suggestions:\033[0m
  • Run \033[33m./vendor/bin/directive --list\033[0m to see available directives
  • Check the spelling of the directive name
  • Make sure the directive file exists in \033[33mapp/Directives/\033[0m
  • Run \033[33mcomposer dump-autoload\033[0m if you just added a new directive

NOTFOUND;
    }

    private function getSuccessContent(): string
    {
        return "\033[32m✓ {{message}}\033[0m\n";
    }

    private function getErrorContent(): string
    {
        return "\033[31m✗ {{message}}\033[0m\n";
    }

    private function getEmptyContent(): string
    {
        return <<<EMPTY

\033[36m═══════════════════════════════════════════════════════════════════════════\033[0m
\033[1;33m⚠️  No Directives Found\033[0m
\033[36m═══════════════════════════════════════════════════════════════════════════\033[0m

\033[37mLet's create your first directive!\033[0m

\033[1;32m📁 Create the directory:\033[0m
  mkdir -p app/Directives

\033[1;32m📝 Create a file \033[33mapp/Directives/HelloDirective.php\033[0m

\033[1;32m🚀 Run your directive:\033[0m
  ./vendor/bin/directive hello "John Doe"

\033[90m💡 Tip: Run './vendor/bin/directive --list' after creating your directive to see it here!\033[0m

\033[36m═══════════════════════════════════════════════════════════════════════════\033[0m

EMPTY;
    }

    private function getConflictContent(): string
    {
        return <<<CONFLICT
\033[33m⚠️ Multiple directives match '{{name}}':\033[0m
{{options}}

CONFLICT;
    }

    private function getTableContent(): string
    {
        return "{{table}}";
    }

    private function getValidationErrorContent(): string
    {
        return <<<VALIDATION_ERROR
\033[31m✗ Error:\033[0m {{error}}

\033[33mValid examples:\033[0m
  • user-create
  • cache-clear
  • api-user-profile

\033[90m💡 Directive names must start with a letter and contain only letters, numbers, and hyphens.\033[0m

VALIDATION_ERROR;
    }

    private function getDisplayMessageContent(): string
    {
        return "{{color}}{{message}}{{reset}}";
    }
}
