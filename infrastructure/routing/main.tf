resource "aws_cloudfront_distribution" "distribution" {
  enabled             = true
  is_ipv6_enabled     = true
  aliases             = ["api.coveragerobot.com"]
  default_root_object = "/"

  origin {
    domain_name = trimprefix(aws_apigatewayv2_api.api_gateway.api_endpoint, "https://")
    origin_id   = "api-gateway"

    custom_origin_config {
      http_port              = 80
      https_port             = 443
      origin_protocol_policy = "https-only"
      origin_ssl_protocols = [
        "TLSv1.2"
      ]
    }
  }

  restrictions {
    geo_restriction {
      restriction_type = "none"
    }
  }

  default_cache_behavior {
    cache_policy_id          = "4135ea2d-6df8-44a3-9df3-4b5a84be39ad"
    origin_request_policy_id = "b689b0a8-53d0-40ab-baf2-68738e2966ac"
    compress                 = true
    target_origin_id         = "api-gateway"
    viewer_protocol_policy   = "redirect-to-https"
    allowed_methods          = ["GET", "HEAD", "OPTIONS", "PUT", "POST", "PATCH", "DELETE"]
    cached_methods           = ["GET", "HEAD"]
  }

  viewer_certificate {
    # The SSL certificate from ACM for CoverageRobot.com
    acm_certificate_arn      = "arn:aws:acm:us-east-1:952005856579:certificate/c821b310-14cd-429b-9ca1-781cda3b84de"
    ssl_support_method       = "sni-only"
    minimum_protocol_version = "TLSv1.2_2021"
  }
}

resource "aws_apigatewayv2_api" "api_gateway" {
  name          = "coverage-${var.environment}"
  protocol_type = "HTTP"
}

resource "aws_apigatewayv2_stage" "api_gateway_stage" {
  api_id      = aws_apigatewayv2_api.api_gateway.id
  name        = "$default"
  auto_deploy = true
}