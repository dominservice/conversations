<?php

namespace Dominservice\Conversations\Tests\Unit;

use Dominservice\Conversations\Tests\TestCase;
use Dominservice\Conversations\Models\Eloquent\ConversationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;

class ConversationTypeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_get_and_set_translations()
    {
        // Create a conversation type with translations
        $type = new ConversationType();
        $type->color = '#FF0000';
        $type->custom = true;
        $type->fill([
            'en' => ['name' => 'Team Chat'],
            'es' => ['name' => 'Chat de equipo'],
        ]);
        $type->save();

        // Test getting translations
        $this->assertEquals('Team Chat', $type->getTranslation('name', 'en'));
        $this->assertEquals('Chat de equipo', $type->getTranslation('name', 'es'));

        // Test getting translation in current locale
        App::setLocale('en');
        $this->assertEquals('Team Chat', $type->name);

        App::setLocale('es');
        $this->assertEquals('Chat de equipo', $type->name);

        // Test setting translation
        $type->translateOrNew('fr')->name = 'Chat d\'équipe';
        $type->save();

        $this->assertEquals('Chat d\'équipe', $type->getTranslation('name', 'fr'));
    }

    /** @test */
    public function it_falls_back_to_default_locale()
    {
        // Set fallback locale
        config(['app.fallback_locale' => 'en']);

        // Create a conversation type with only English translation
        $type = new ConversationType();
        $type->color = '#00FF00';
        $type->custom = true;
        $type->fill([
            'en' => ['name' => 'Support Chat'],
        ]);
        $type->save();

        // Test fallback to English when Spanish is not available
        App::setLocale('es');
        $this->assertEquals('Support Chat', $type->name);
    }
}