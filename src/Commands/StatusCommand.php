<?php

namespace NextApps\PoeditorSync\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use NextApps\PoeditorSync\Poeditor\Poeditor;
use NextApps\PoeditorSync\Translations\TranslationManager;

class StatusCommand extends Command
{
    protected $signature = 'poeditor:status';

    protected $description = 'Check if local translations match the ones on POEditor';

    public function handle() : int
    {
        $isOutdated = $this->getLocales()->map(function ($locale, $key) {
            $poeditorTranslations = app(Poeditor::class)->download(is_string($key) ? $key : $locale);

            return collect($locale)->map(function ($internalLocale) use ($poeditorTranslations) {
                $localTranslations = app(TranslationManager::class)->getTranslations($internalLocale);

                $poeditorTranslations = collect($poeditorTranslations)->dot()->sortKeys();
                $localTranslations = collect($localTranslations)->dot()->sortKeys();

                if ($poeditorTranslations->toArray() === $localTranslations->toArray()) {
                    return true;
                }

                $this->error("The translations for '{$internalLocale}' do not match the ones on POEditor.");

                $outdatedLocalTranslations = $poeditorTranslations->diff($localTranslations);
                $outdatedPoeditorTranslations = $localTranslations->diff($poeditorTranslations);

                $this->table(
                    ['Translation Key'],
                    $outdatedLocalTranslations->merge($outdatedPoeditorTranslations)
                        ->keys()
                        ->unique()
                        ->map(fn ($key) => [$key])
                        ->all()
                );

                return false;
            });
        })->flatten()->contains(false);

        if ($isOutdated) {
            return Command::FAILURE;
        }

        $this->info('All translations match the ones on POEditor!');

        return Command::SUCCESS;
    }

    protected function getLocales() : Collection
    {
        return collect(config('poeditor-sync.locales'));
    }
}
