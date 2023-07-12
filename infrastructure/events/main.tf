resource "aws_cloudwatch_event_bus" "coverage_events_bus" {
  name = "coverage-events-${var.environment}"
}