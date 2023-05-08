resource "aws_iam_role" "lambda_role" {
    name               = "coverage-services-lambda-role"
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

resource "aws_iam_policy" "logging_policy" {
    name   = "lambda-coverage-logging-policy"
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
            }
        ]
    })
}

resource "aws_iam_role_policy_attachment" "attach_lamdba_logging_policy" {
    role       = aws_iam_role.lambda_role.name
    policy_arn = aws_iam_policy.logging_policy.arn
}

resource "aws_sqs_queue" "coverage_analysis_queue" {
    name = format("coverage-analysis-queue-%s", var.environment)

    redrive_policy = jsonencode({
        deadLetterTargetArn = aws_sqs_queue.coverage_analysis_deadletter_queue.arn
        maxReceiveCount     = 1
    })
}

resource "aws_sqs_queue" "coverage_analysis_deadletter_queue" {
    name = format("coverage-analysis-deadletter-queue-%s", var.environment)

    # Store deadletter messages for 2 weeks
    message_retention_seconds = 1209600
}

resource "aws_s3_bucket" "coverage_ingest" {
    bucket = format("coverage-ingest-%s", var.environment)

    tags = {
        environment = var.environment
    }
}

resource "aws_s3_bucket" "coverage_output" {
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

    ingest_bucket = aws_s3_bucket.coverage_ingest
    output_bucket = aws_s3_bucket.coverage_output

    # The layer version needs to be kept inline with Bref's release, so that the layers
    # match the runtime. See https://runtimes.bref.sh/?region=eu-west-2&version=2.0.4.
    bref_layer_version = "21"

    depends_on = [
        aws_iam_role_policy_attachment.attach_lamdba_logging_policy
    ]
}

module "analyse" {
    source      = "../../services/analyse/infrastructure"
    environment = var.environment
    lambda_role = aws_iam_role.lambda_role
    region      = var.region

    analysis_queue = aws_sqs_queue.coverage_analysis_queue

    # The layer version needs to be kept inline with Bref's release, so that the layers
    # match the runtime. See https://runtimes.bref.sh/?region=eu-west-2&version=2.0.4.
    bref_layer_version = "21"

    depends_on = [
        aws_iam_role_policy_attachment.attach_lamdba_logging_policy
    ]
}