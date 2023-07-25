output "cloudwatch_distribution" {
  value = aws_cloudfront_distribution.distribution
}

output "api_gateway" {
  value = aws_apigatewayv2_api.api_gateway
}

output "api_gateway_default_stage" {
  value = aws_apigatewayv2_stage.api_gateway_stage
}