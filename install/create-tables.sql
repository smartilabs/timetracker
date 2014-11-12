CREATE TABLE `TaskType` (
  `TaskTypeID` INT         NOT NULL AUTO_INCREMENT,
  `Name`       VARCHAR(50) NOT NULL,
  PRIMARY KEY (`TaskTypeID`)
);

CREATE TABLE `Module` (
  `ModuleID` INT         NOT NULL AUTO_INCREMENT,
  `Name`     VARCHAR(50) NOT NULL,
  PRIMARY KEY (`ModuleID`)
);

CREATE TABLE `User` (
  `UserID`   INT                                    NOT NULL AUTO_INCREMENT,
  `Username` VARCHAR(255)                           NOT NULL,
  `Password` VARCHAR(255)                           NOT NULL,
  `Name`     VARCHAR(255),
  `RoleID`   ENUM('user', 'admin') DEFAULT 'user'   NOT NULL,
  PRIMARY KEY (`UserID`)
);

CREATE TABLE `Track` (
  `TrackID`     INT                                     NOT NULL AUTO_INCREMENT,
  `UserID`      INT                                     NOT NULL,
  `Location`    ENUM('Office', 'Home') DEFAULT 'Office' NOT NULL,
  `TimeStart`   DATETIME                                NULL,
  `TimeEnd`     DATETIME                                NULL,
  `Ticket`      INT,
  `TaskTypeID`  INT                                     NULL,
  `ModuleID`    INT,
  `Description` VARCHAR(500)                            NOT NULL,
  `IsDeleted`   SMALLINT(1) DEFAULT 0                   NOT NULL,
  PRIMARY KEY (`TrackID`),
  INDEX `IX_Track_IsDeleted` (`IsDeleted`),
  CONSTRAINT `FK_Track_Module` FOREIGN KEY (`ModuleID`) REFERENCES `Module` (`ModuleID`),
  CONSTRAINT `FK_Track_User` FOREIGN KEY (`UserID`) REFERENCES `User` (`UserID`),
  CONSTRAINT `FK_Track_TaskType` FOREIGN KEY (`TaskTypeID`) REFERENCES `TaskType` (`TaskTypeID`)
);
