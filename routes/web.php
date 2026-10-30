<?php

use App\Http\Controllers\DatasetController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\TranscriptionController;
use App\Http\Controllers\VoiceController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/generations');

Route::get('/voices', [VoiceController::class, 'index'])->name('voices.index');
Route::post('/voices', [VoiceController::class, 'store'])->name('voices.store');
Route::put('/voices/{voice}', [VoiceController::class, 'update'])->name('voices.update');
Route::delete('/voices/{voice}', [VoiceController::class, 'destroy'])->name('voices.destroy');
Route::delete('/voices', [VoiceController::class, 'bulkDestroy'])->name('voices.bulk-destroy');
Route::delete('/voices/{voice}/samples', [VoiceController::class, 'bulkDestroySamples'])->name('voices.samples.bulk-destroy');
Route::post('/voices/{voice}/samples', [VoiceController::class, 'storeSamples'])->name('voices.samples.store');
Route::delete('/voice-samples/{sample}', [VoiceController::class, 'destroySample'])->name('voices.samples.destroy');
Route::put('/voice-samples/{sample}/clip', [VoiceController::class, 'updateClip'])->name('voices.samples.clip.update');
Route::post('/voice-samples/{sample}/auto-clip', [VoiceController::class, 'autoClip'])->name('voices.samples.clip.auto');
Route::get('/voice-samples/{sample}/clip', [VoiceController::class, 'sampleClip'])->name('voices.samples.clip');
Route::get('/voice-samples/{sample}/audio', [VoiceController::class, 'sampleAudio'])->name('voices.samples.audio');

Route::get('/voices/{voice}/dataset', [DatasetController::class, 'show'])->name('datasets.show');
Route::post('/voices/{voice}/dataset/build', [DatasetController::class, 'build'])->name('datasets.build');
Route::put('/voices/{voice}/dataset', [DatasetController::class, 'update'])->name('datasets.update');
Route::get('/voices/{voice}/dataset/export', [DatasetController::class, 'export'])->name('datasets.export');
Route::get('/dataset-clips/{clip}/audio', [DatasetController::class, 'audio'])->name('datasets.audio');

Route::get('/generations', [GenerationController::class, 'index'])->name('generations.index');
Route::get('/generations/list', [GenerationController::class, 'list'])->name('generations.list');
Route::post('/generations', [GenerationController::class, 'store'])->name('generations.store');
Route::get('/generations/{generation}', [GenerationController::class, 'show'])->name('generations.show');
Route::get('/generations/{generation}/audio', [GenerationController::class, 'audio'])->name('generations.audio');
Route::get('/generations/{generation}/download', [GenerationController::class, 'download'])->name('generations.download');
Route::post('/generations/{generation}/retry', [GenerationController::class, 'retry'])->name('generations.retry');
Route::delete('/generations/{generation}', [GenerationController::class, 'destroy'])->name('generations.destroy');
Route::delete('/generations', [GenerationController::class, 'bulkDestroy'])->name('generations.bulk-destroy');

Route::get('/youtube', [TranscriptionController::class, 'index'])->name('transcriptions.index');
Route::get('/youtube/list', [TranscriptionController::class, 'list'])->name('transcriptions.list');
Route::post('/youtube', [TranscriptionController::class, 'store'])->name('transcriptions.store');
Route::get('/youtube/{transcription}', [TranscriptionController::class, 'show'])->name('transcriptions.show');
Route::put('/youtube/{transcription}', [TranscriptionController::class, 'update'])->name('transcriptions.update');
Route::post('/youtube/{transcription}/retry', [TranscriptionController::class, 'retry'])->name('transcriptions.retry');
Route::delete('/youtube/{transcription}', [TranscriptionController::class, 'destroy'])->name('transcriptions.destroy');
Route::delete('/youtube', [TranscriptionController::class, 'bulkDestroy'])->name('transcriptions.bulk-destroy');
