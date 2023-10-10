resource "google_bigquery_dataset" "environment_dataset" {
    dataset_id    = var.environment
    friendly_name = var.environment
    location      = "EU"
    description   = "Dataset for ${var.environment} environment"

    labels = {
        environment = var.environment
    }
}

resource "google_bigquery_table" "line_coverage" {
    dataset_id          = google_bigquery_dataset.environment_dataset.dataset_id
    table_id            = "line_coverage"
    deletion_protection = false
    clustering          = [
        "provider",
        "owner",
        "repository",
        "commit",
    ]

    schema = <<EOF
[
  {
    "name": "uploadId",
    "type": "STRING",
    "mode": "REQUIRED",
    "description": "The unique upload id for the uploaded file."
  },
  {
    "name": "commit",
    "type": "STRING",
    "mode": "REQUIRED",
    "description": "The commit hash which the coverage was generated for."
  },
  {
    "name": "parent",
    "type": "STRING",
    "mode": "REPEATED",
    "description": "The parent commit hash(es) of the commit the coverage was generated for."
  },
  {
    "name": "ref",
    "type": "STRING",
    "mode": "REQUIRED",
    "description": "The ref (e.g. branch) in the VCS provider."
  },
  {
    "name": "sourceFormat",
    "type": "STRING",
    "mode": "REQUIRED",
    "description": "The original format of the coverage file ingested."
  },
  {
    "name": "totalLines",
    "type": "INTEGER",
    "mode": "REQUIRED",
    "description": "The total number of lines ingested from the coverage file."
  },
  {
    "name": "fileName",
    "type": "STRING",
    "mode": "REQUIRED",
    "description": "The path of the file which the line exists in."
  },
  {
    "name": "lineNumber",
    "type": "INTEGER",
    "mode": "REQUIRED",
    "description": "The number of the line."
  },
  {
    "name": "type",
    "type": "STRING",
    "mode": "REQUIRED",
    "description": "The type of the line coverage."
  },
  {
    "name": "tag",
    "type": "STRING",
    "mode": "REQUIRED",
    "description": "The user provided tag of the coverage file."
  },
  {
    "name": "owner",
    "type": "STRING",
    "mode": "REQUIRED",
    "description": "The repository owner for the VCS provider."
  },
  {
    "name": "repository",
    "type": "STRING",
    "mode": "REQUIRED",
    "description": "The repository name for the VCS provider."
  },
  {
    "name": "provider",
    "type": "STRING",
    "mode": "REQUIRED",
    "description": "The VCS provider."
  },
  {
    "name": "metadata",
    "type": "RECORD",
    "mode": "REPEATED",
    "description": "The lines metadata.",
    "fields": [
        {
            "name": "key",
            "type": "STRING",
            "mode": "REQUIRED"
        },
        {
            "name": "value",
            "type": "STRING",
            "mode": "NULLABLE"
        }
    ]
  },
  {
    "name": "generatedAt",
    "type": "DATETIME",
    "mode": "NULLABLE",
    "description": "The time the coverage was generated."
  },
  {
    "name": "ingestTime",
    "type": "DATETIME",
    "mode": "NULLABLE",
    "description": "The number of the line."
  }
]
EOF
}

resource "google_bigquery_table" "upload" {
    dataset_id          = google_bigquery_dataset.environment_dataset.dataset_id
    table_id            = "upload"
    deletion_protection = false
    clustering          = [
        "provider",
        "owner",
        "repository",
        "commit",
    ]

    schema = <<EOF
[
  {
    "name": "uploadId",
    "type": "STRING",
    "mode": "REQUIRED",
    "description": "The unique upload id for the uploaded file."
  },
  {
    "name": "commit",
    "type": "STRING",
    "mode": "REQUIRED",
    "description": "The commit hash which the upload occured on."
  },
  {
    "name": "parent",
    "type": "STRING",
    "mode": "REPEATED",
    "description": "The parent commit hash(es) of the commit upload occured on."
  },
  {
    "name": "ref",
    "type": "STRING",
    "mode": "REQUIRED",
    "description": "The ref (e.g. branch) in the VCS provider."
  },
  {
    "name": "tag",
    "type": "STRING",
    "mode": "REQUIRED",
    "description": "The user provided tag of the upload."
  },
  {
    "name": "owner",
    "type": "STRING",
    "mode": "REQUIRED",
    "description": "The repository owner for the VCS provider."
  },
  {
    "name": "repository",
    "type": "STRING",
    "mode": "REQUIRED",
    "description": "The repository name for the VCS provider."
  },
  {
    "name": "provider",
    "type": "STRING",
    "mode": "REQUIRED",
    "description": "The VCS provider."
  },
  {
    "name": "ingestTime",
    "type": "DATETIME",
    "mode": "NULLABLE",
    "description": "The time the upload occurred."
  }
]
EOF
}

resource "google_storage_bucket" "loadable_data_bucket" {
    name = format("coverage-loadable-data-%s", var.environment)

    # GCP has a always-free tier which only applies to a handful of US
    # regions. Meaning we can't use the free tier for our bucket if its
    # in the EU.
    location = "us-east1"

    # Files can be deleted very quickly. Really they should be deleted
    # immediately, but a lifecycle for now should do.
    lifecycle_rule {
        condition {
            age = 1
        }
        action {
            type = "Delete"
        }
    }
}