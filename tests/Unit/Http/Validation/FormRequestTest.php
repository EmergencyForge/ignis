<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Validation;

use App\Http\Request;
use App\Http\Validation\FormRequest;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Respect\Validation\Validator as v;
use Tests\TestCase;

class FormRequestTest extends TestCase
{
    private function postJson(array $body): Request
    {
        return new Request(
            method:  'POST',
            path:    '/test',
            rawBody: json_encode($body),
        );
    }

    #[Test]
    public function validate_returns_sanitized_data_when_all_rules_pass(): void
    {
        $req = $this->postJson([
            'name'  => 'Alice',
            'age'   => 30,
            'extra' => 'should be dropped',
        ]);

        $result = DummyFormRequest::validate($req);

        $this->assertSame('Alice', $result['name']);
        $this->assertSame(30, $result['age']);
        // Extra fields that aren't in rules() get dropped
        $this->assertArrayNotHasKey('extra', $result);
    }

    #[Test]
    public function validate_throws_with_field_errors_when_rules_fail(): void
    {
        $req = $this->postJson([
            'name' => '',     // notEmpty fails
            'age'  => 'abc',  // intType fails
        ]);

        try {
            DummyFormRequest::validate($req);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->getCode());
            $this->assertArrayHasKey('name', $e->errors());
            $this->assertArrayHasKey('age', $e->errors());
        }
    }

    #[Test]
    public function validate_uses_custom_messages_when_provided(): void
    {
        $req = $this->postJson(['name' => '', 'age' => 5]);

        try {
            DummyFormRequest::validate($req);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('Name darf nicht leer sein', $e->errors()['name']);
        }
    }

    #[Test]
    public function validate_handles_missing_fields_as_errors(): void
    {
        $req = $this->postJson([]); // keine Felder

        try {
            DummyFormRequest::validate($req);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->errors());
            $this->assertArrayHasKey('age', $e->errors());
        }
    }

    #[Test]
    public function validate_reads_query_params_for_get_requests(): void
    {
        $req = new Request(
            method: 'GET',
            path:   '/test',
            query:  ['name' => 'Bob', 'age' => 25],
        );

        $result = DummyFormRequest::validate($req);

        $this->assertSame('Bob', $result['name']);
        $this->assertSame(25, $result['age']);
    }
}

final class DummyFormRequest extends FormRequest
{
    protected function rules(): array
    {
        return [
            'name' => v::stringType()->notEmpty()->length(1, 100),
            'age'  => v::intType()->min(18),
        ];
    }

    protected function messages(): array
    {
        return [
            'name' => 'Name darf nicht leer sein',
        ];
    }
}
