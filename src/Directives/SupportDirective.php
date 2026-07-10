<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class SupportDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'support {--all}';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['sponsor', 'help-me']);
    }

    public function getDescription(): string
    {
        return 'Show ways to support Andy Kani\'s open source work';
    }

    public function execute(): ExitCode
    {
        $all = $this->flag('all');

        $this->line('');
        $this->info('🌟 Support Open Source Work');
        $this->separator('=', 40);
        $this->line('');

        if ($all) {
            $this->displayAllWays();
        } else {
            $this->displayQuickWays();
        }

        $this->line('');
        $this->info('🙏 Every contribution matters!');
        $this->line('');

        return ExitCode::SUCCESS;
    }

    private function displayQuickWays(): void
    {
        $this->line('📌 Quick ways to support:');
        $this->newLine();

        $this->line('  ⭐ Star the repository on GitHub');
        $this->line('  🐛 Report issues you find');
        $this->line('  💡 Suggest new features');
        $this->line('  📝 Improve documentation');
        $this->line('  🔀 Submit pull requests');

        $this->newLine();
        $this->line('💖 For financial support:');
        $this->line('  Use --all to see all options');
    }

    private function displayAllWays(): void
    {
        $this->line('👤 About Andy Kani');
        $this->separator('-', 30);
        $this->line('  Fullstack Developer');
        $this->line('  PHP   • JS/TS • Kotlin');
        $this->line('  React • Vue   • Laravel');
        $this->line('  DevOps enthusiast');
        $this->newLine();

        $this->line('🌐 Connect & Support');
        $this->separator('-', 30);

        $this->displayRow('GitHub', 'github.com/andydefer');
        $this->displayRow('LinkedIn', 'in/andy-kani-3751a1249');
        $this->displayRow('WhatsApp', '+243 827 833 329');
        $this->displayRow('Facebook', 'profile.php?id=100088554107596');
        $this->newLine();

        $this->line('💝 Ways to support:');
        $this->separator('-', 30);

        $this->line('  1. ⭐ Star repositories');
        $this->line('  2. 🐛 Report bugs');
        $this->line('  3. 💡 Suggest features');
        $this->line('  4. 📝 Write documentation');
        $this->line('  5. 🔀 Contribute code');
        $this->line('  6. 💰 Financial support');
        $this->line('  7. 📢 Share with your network');
        $this->line('  8. 🏗️ Use the packages in your projects');
        $this->newLine();

        $this->line('📦 Packages:');
        $this->line('  • laravel-directive');
        $this->line('  • laravel-task');
        $this->line('  • php-signature-parser');
        $this->line('  • php-console-writer');
        $this->line('  • domain-structures');
        $this->line('  • and more...');
    }

    private function displayRow(string $label, string $value): void
    {
        $this->line(sprintf('  %-12s: %s', $label, $value));
    }
}
