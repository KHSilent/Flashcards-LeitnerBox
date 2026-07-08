<?php

namespace Database\Seeders;

use App\Models\CategoryAccess;
use App\Models\Flashcard;
use App\Models\FlashcardCategory;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::query()->firstOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'password' => Hash::make('password'),
            'is_active' => true,
            'roles' => ['manageUser'],
        ]);

        $user->forceFill([
            'is_active' => true,
            'roles' => array_values(array_unique([...(array) ($user->roles ?: []), 'manageUser'])),
        ])->save();

        $english = FlashcardCategory::query()->firstOrCreate(['parent_id' => null, 'name' => 'زبان انگلیسی']);
        $lessonOne = FlashcardCategory::query()->firstOrCreate(['parent_id' => $english->id, 'name' => 'Lesson 1']);
        $unitOne = FlashcardCategory::query()->firstOrCreate(['parent_id' => $lessonOne->id, 'name' => 'Unit 1']);

        foreach ([$english, $lessonOne, $unitOne] as $category) {
            CategoryAccess::query()->firstOrCreate([
                'user_id' => $user->id,
                'flashcard_category_id' => $category->id,
            ], [
                'can_edit' => true,
                'steps' => CategoryAccess::DEFAULT_STEPS,
            ]);
        }

        if ($unitOne->flashcards()->count() === 0) {
            collect([
                ['Apple', 'سیب'],
                ['Book', 'کتاب'],
                ['Chair', 'صندلی'],
                ['Water', 'آب'],
                ['Window', 'پنجره'],
                ['Teacher', 'معلم'],
                ['Student', 'دانش‌آموز'],
                ['Morning', 'صبح'],
                ['Question', 'سوال'],
                ['Answer', 'جواب'],
                ['Remember', 'به خاطر آوردن'],
                ['Practice', 'تمرین کردن'],
            ])->each(function (array $pair) use ($unitOne) {
                $flashcard = Flashcard::query()->create([
                    'flashcard_category_id' => $unitOne->id,
                    'title' => $pair[0],
                ]);

                $flashcard->sides()->createMany([
                    ['side_number' => 1, 'content' => $pair[0]],
                    ['side_number' => 2, 'content' => $pair[1]],
                ]);
            });
        }
    }
}
