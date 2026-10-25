<?php

declare(strict_types=1);

namespace Atannex\Localization\Filament;

use Atannex\Localization\Concerns\HasTranslations;
use Atannex\Localization\Http\Middleware\SetLocale;
use Atannex\Localization\Localization;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Panel;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament (^4) integration:
 *
 *   - a language switcher in the topbar (same POST route as the site's),
 *   - a banner above create/edit pages of translatable resources saying
 *     which language version is being edited,
 *   - no fallback inside the panel, so editors see untranslated fields
 *     as empty and saving never copies one language into another,
 *   - relationship selects and filters show translated titles instead of
 *     the raw {"en": …} JSON,
 *   - case-insensitive table search (JSON columns compare binary on MySQL).
 *
 *     $panel->plugin(LocalizationPlugin::make())
 */
final class LocalizationPlugin implements Plugin
{
    private string|false $switcherHook = PanelsRenderHook::USER_MENU_BEFORE;

    private bool $editingBanner = true;

    private bool $strict = true;

    private bool $strictReads = false;

    private bool $translateOptions = true;

    private bool $caseInsensitiveSearch = true;

    private bool $setLocale = false;

    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'atannex-localization';
    }

    /** Where the switcher renders (a PanelsRenderHook name), or false for none. */
    public function switcher(string|bool $hook = true): self
    {
        $this->switcherHook = $hook === true ? PanelsRenderHook::USER_MENU_BEFORE : $hook;

        return $this;
    }

    public function editingBanner(bool $enabled = true): self
    {
        $this->editingBanner = $enabled;

        return $this;
    }

    /**
     * No fallback translations in the panel's forms (and, with $reads, in
     * tables, titles and everywhere else attributes are read).
     */
    public function strict(bool $enabled = true, bool $reads = false): self
    {
        $this->strict = $enabled;
        $this->strictReads = $reads;

        return $this;
    }

    public function translateRelationshipOptions(bool $enabled = true): self
    {
        $this->translateOptions = $enabled;

        return $this;
    }

    public function caseInsensitiveSearch(bool $enabled = true): self
    {
        $this->caseInsensitiveSearch = $enabled;

        return $this;
    }

    /**
     * Append the SetLocale middleware to the panel. Off by default: list it
     * yourself before DispatchServingFilamentEvent so serving callbacks
     * already run in the right locale.
     */
    public function setLocale(bool $enabled = true): self
    {
        $this->setLocale = $enabled;

        return $this;
    }

    public function register(Panel $panel): void
    {
        if ($this->setLocale) {
            $panel->middleware([SetLocale::class], isPersistent: true);
        }

        if ($this->switcherHook !== false) {
            $panel->renderHook($this->switcherHook, fn (): string => view('localization::filament.switcher')->render());
        }

        // Registered here rather than in boot(): the condition itself checks that
        // the panel is serving, and boot() only runs once panel middleware has.
        if ($this->strict) {
            $id = $panel->getId();

            app(Localization::class)->strictWhen(
                fn (): bool => Filament::isServing() && Filament::getCurrentPanel()?->getId() === $id,
                $this->strictReads,
            );
        }

        if ($this->editingBanner) {
            $panel->renderHook(PanelsRenderHook::PAGE_START, fn (array $scopes = []): string => $this->banner($scopes));
        }
    }

    public function boot(Panel $panel): void
    {

        if ($this->translateOptions) {
            $label = static fn (Model $record, ?string $attribute): string => (string) ($attribute ? $record->getAttribute($attribute) : $record->getKey());

            Select::configureUsing(fn (Select $select) => $select->getOptionLabelFromRecordUsing(
                fn (Model $record) => $label($record, $select->getRelationshipTitleAttribute()),
            ));

            SelectFilter::configureUsing(fn (SelectFilter $filter) => $filter->getOptionLabelFromRecordUsing(
                fn (Model $record) => $label($record, $filter->getRelationshipTitleAttribute()),
            ));
        }

        if ($this->caseInsensitiveSearch) {
            TextColumn::configureUsing(fn (TextColumn $column) => $column->forceSearchCaseInsensitive());
        }
    }

    /**
     * @param  array<int, mixed>  $scopes
     */
    private function banner(array $scopes): string
    {
        $localization = app(Localization::class);

        if (! $localization->isMultilingual()) {
            return '';
        }

        foreach ($scopes as $page) {
            if (! is_string($page) || ! (is_subclass_of($page, EditRecord::class) || is_subclass_of($page, CreateRecord::class))) {
                continue;
            }

            $model = $page::getResource()::getModel();

            if (in_array(HasTranslations::class, class_uses_recursive($model), true)) {
                return view('localization::filament.editing-banner', [
                    'locale' => $localization->current(),
                    'default' => $localization->default(),
                ])->render();
            }
        }

        return '';
    }
}
