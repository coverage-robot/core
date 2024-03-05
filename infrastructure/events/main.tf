resource "aws_cloudwatch_event_bus" "coverage_events_bus" {
  name = "coverage-events-${var.environment}"
}

resource "aws_iam_role" "coverage_event_scheduler_role" {
  name = "coverage-event-scheduler-role-${var.environment}"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Action = "sts:AssumeRole"
        Effect = "Allow"
        Principal = {
          Service = "scheduler.amazonaws.com"
        }
      }
    ]
  })
}

resource "aws_iam_policy" "coverage_event_scheduler_policy" {
  name = "coverage-event-scheduler-policy-${var.environment}"
  path = "/"
  policy = jsonencode({
    "Version" = "2012-10-17",
    "Statement" = [
      {
        "Effect" = "Allow",
        "Action" = [
          "events:PutEvents"
        ],
        "Resource" = [
          aws_cloudwatch_event_bus.coverage_events_bus
        ]
      }
    ]
  })
}

resource "aws_iam_role_policy_attachment" "coverage_event_scheduler_policy_attachment" {
  role       = aws_iam_role.coverage_event_scheduler_role.name
  policy_arn = aws_iam_policy.coverage_event_scheduler_policy.arn
}