<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class LegalDocumentController extends Controller
{
    public function api(string $document): JsonResponse
    {
        return response()->json(['data' => $this->document($document)]);
    }

    public function web(string $document): View
    {
        return view('legal.show', ['document' => $this->document($document)]);
    }

    public function terms(): View
    {
        return $this->web('terms');
    }

    public function privacy(): View
    {
        return $this->web('privacy');
    }

    private function document(string $key): array
    {
        abort_unless(in_array($key, ['terms', 'privacy'], true), 404);
        $document = config("legal.documents.{$key}");
        $replacements = [
            ':operator_name' => (string) config('legal.operator_name'),
            ':operator_address' => (string) config('legal.operator_address'),
            ':contact_email' => (string) config('legal.contact_email'),
            ':contact_phone' => (string) config('legal.contact_phone'),
            ':minimum_age' => (string) config('legal.minimum_age'),
        ];

        $document['key'] = $key;
        $document['operator'] = [
            'name' => config('legal.operator_name'),
            'address' => config('legal.operator_address'),
            'email' => config('legal.contact_email'),
            'phone' => config('legal.contact_phone'),
            'phone_uri' => config('legal.contact_phone_uri'),
        ];
        $document['summary'] = strtr($document['summary'], $replacements);
        $document['sections'] = array_map(function (array $section) use ($replacements) {
            $section['paragraphs'] = array_map(fn (string $text) => strtr($text, $replacements), $section['paragraphs']);
            return $section;
        }, $document['sections']);

        return $document;
    }
}
