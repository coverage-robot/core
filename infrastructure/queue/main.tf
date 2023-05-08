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