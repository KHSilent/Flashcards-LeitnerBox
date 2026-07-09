<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FlashcardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StudyController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::get('/csrf-token', [AuthController::class, 'csrfToken']);
    Route::post('/login', [AuthController::class, 'login'])->middleware(['guest','throttle:5,1']);

    Route::middleware('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::put('/profile/password', [ProfileController::class, 'updatePassword']);

        Route::get('/categories', [CategoryController::class, 'index']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::get('/categories/{category}', [CategoryController::class, 'show']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
        Route::post('/categories/{category}/introduce', [CategoryController::class, 'introduce']);
        Route::put('/categories/{category}/steps', [CategoryController::class, 'updateSteps']);
        Route::get('/categories/{category}/flashcards', [FlashcardController::class, 'index']);
        Route::post('/categories/{category}/flashcards', [FlashcardController::class, 'store']);
        Route::post('/categories/{category}/flashcards/{flashcard}/smart-process', [FlashcardController::class, 'smartProcess']);
        Route::post('/categories/{category}/flashcards/{flashcard}', [FlashcardController::class, 'update']);
        Route::put('/categories/{category}/flashcards/{flashcard}', [FlashcardController::class, 'update']);
        Route::delete('/categories/{category}/flashcards/{flashcard}', [FlashcardController::class, 'destroy']);

        Route::get('/categories/{category}/study', [StudyController::class, 'index']);
        Route::get('/categories/{category}/study-cards', [StudyController::class, 'stepCards']);
        Route::post('/categories/{category}/study-cards/{studyCard}/answer', [StudyController::class, 'answer']);

        Route::put('/users/{user}/category-accesses', [UserController::class, 'updateCategoryAccesses']);
        Route::apiResource('users', UserController::class);
    });
});

Route::view('/{any?}', 'app')->where('any', '.*');
