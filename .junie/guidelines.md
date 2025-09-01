## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to the existing directory structure. Do not create new base folders without approval.
- Do not change the application's dependencies without approval.

## Replies
- Be concise in your explanations. Focus on what's important rather than details obvious to an experienced developer.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.

## Pest

### Testing
- If you need to verify a feature is working, write or update a `Unit` or `Feature` test.
- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.

### Pest Tests
- All tests must be written using Pest.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files. These are core to the application.
- Tests should test all of the happy paths, failure paths, and weird paths.
- Tests live in the `tests/Feature` and `tests/Unit` directories.
- Pest tests look and behave like this:
```php
it('is true', function () {
    expect(true)->toBeTrue();
});
```

### Running Tests
- Run the minimal number of tests using an appropriate filter before finalizing code edits.
- To run all tests: `vendor/bin/pest`.
- To run all tests in a file: `vendor/bin/pest tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `vendor/bin/pest --filter=testName` (recommended after making a change to a related file).
- When the tests relating to your changes are passing, run the entire test suite.
