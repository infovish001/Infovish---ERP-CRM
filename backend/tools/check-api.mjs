import SwaggerParser from '@apidevtools/swagger-parser';

await SwaggerParser.validate('../docs/api/API_SPECIFICATION.yaml');
console.log('PASS: foundation OpenAPI contract validated.');
