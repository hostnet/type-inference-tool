-- Type-Inference-Tool database definition

CREATE TABLE IF NOT EXISTS entry_record (
  execution_id     CHAR(13)     NOT NULL,
  function_nr      INT          NOT NULL,
  function_name    VARCHAR(256) NOT NULL,
  is_user_defined  BIT          NOT NULL,
  file_name        VARCHAR(512) NOT NULL,
  declaration_file VARCHAR(512) NULL,
  CONSTRAINT pk_entry_record PRIMARY KEY (execution_id, function_nr)
);

CREATE TABLE IF NOT EXISTS entry_record_parameter (
  execution_id CHAR(13)     NOT NULL,
  function_nr  INT          NOT NULL,
  param_number INT          NOT NULL,
  param_type   VARCHAR(512) NOT NULL,
  CONSTRAINT pk_entry_record_parameter PRIMARY KEY (execution_id, function_nr, param_number),
  CONSTRAINT fk_entry_record_parameter_entry_record FOREIGN KEY (execution_id, function_nr)
  REFERENCES entry_record (execution_id, function_nr)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS return_record (
  execution_id CHAR(13)     NOT NULL,
  function_nr  INT          NOT NULL,
  return_type  VARCHAR(512) NOT NULL,
  CONSTRAINT pk_return_record PRIMARY KEY (execution_id, function_nr),
  CONSTRAINT fk_return_record_entry_record FOREIGN KEY (execution_id, function_nr)
  REFERENCES entry_record (execution_id, function_nr)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);