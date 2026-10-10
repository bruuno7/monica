<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Gift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Gift>
 */
class GiftFactory extends Factory
{
    protected $model = Gift::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'contact_id' => Contact::factory(),
            'type' => Gift::TYPE_TO_GIVE,
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence,
        ];
    }
}
