resource "aws_iam_role" "lambda_role" {
    name               = "coverage_services_lambda_role"
    assume_role_policy = jsonencode({
        Version   = "2012-10-17"
        Statement = [
            {
                Action    = "sts:AssumeRole"
                Effect    = "Allow"
                Principal = {
                    Service = "lambda.amazonaws.com"
                }
            }
        ]
    })
}

resource "aws_iam_policy" "iam_policy_for_lambda" {
    name   = "coverage_services_lambda_role_policy"
    path   = "/"
    policy = jsonencode({
        Version   = "2012-10-17"
        Statement = [
            {
                Effect = "Allow"
                Action = [
                    "logs:CreateLogGroup",
                    "logs:CreateLogStream",
                    "logs:PutLogEvents"
                ]
                Resource = ["arn:aws:logs:*:*:*"]
            },
            {
                Effect = "Allow"
                Action = [
                    "s3:PutObject"
                ]
                Resource = [
                    "${aws_s3_bucket.coverage-output.arn}/*"
                ]
            }
        ]
    })
}

resource "aws_iam_role_policy_attachment" "attach_iam_policy_to_iam_role" {
    role       = aws_iam_role.lambda_role.name
    policy_arn = aws_iam_policy.iam_policy_for_lambda.arn
}

resource "aws_s3_bucket" "coverage-ingest" {
    bucket = format("coverage-ingest-%s", var.environment)

    tags = {
        environment = var.environment
    }
}

resource "aws_s3_bucket" "coverage-output" {
    bucket = format("coverage-output-%s", var.environment)

    tags = {
        environment = var.environment
    }
}

module "ingest" {
    source      = "../../services/ingest/infrastructure"
    environment = var.environment
    lambda_role = aws_iam_role.lambda_role.arn
    region      = var.region

    ingest_bucket = aws_s3_bucket.coverage-ingest
    output_bucket = aws_s3_bucket.coverage-output

    # The layer version needs to be kept inline with Bref's release, so that the layers
    # match the runtime. See https://runtimes.bref.sh/?region=eu-west-2&version=2.0.4.
    bref_layer_version = "21"

    depends_on = [
        aws_iam_role_policy_attachment.attach_iam_policy_to_iam_role
    ]
}

module "analyse" {
    source      = "../../services/analyse/infrastructure"
    environment = var.environment
    lambda_role = aws_iam_role.lambda_role.arn
    region      = var.region

    # The layer version needs to be kept inline with Bref's release, so that the layers
    # match the runtime. See https://runtimes.bref.sh/?region=eu-west-2&version=2.0.4.
    bref_layer_version = "21"

    depends_on = [
        aws_iam_role_policy_attachment.attach_iam_policy_to_iam_role
    ]
}