from sqlalchemy import (Column, Integer, String, Float, Boolean, DateTime,
                        ForeignKey, Text, JSON, Enum as SAEnum)
from sqlalchemy.orm import relationship
from datetime import datetime
from database import Base
import enum

class VolumeType(str, enum.Enum):
    PROTO = "PROTO"
    VL1   = "VL1"
    VL2   = "VL2"

class Project(Base):
    __tablename__ = "projects"
    id          = Column(Integer, primary_key=True)
    code        = Column(String(64), unique=True, index=True)   # CE-005646
    customer    = Column(String(256))
    description = Column(String(512))
    currency    = Column(String(8), default="USD")
    eur_rate    = Column(Float, default=0.8585)
    inr_rate    = Column(Float, default=89.47)
    usd_rate    = Column(Float, default=1.0)
    proto_qty   = Column(Integer, default=10)
    vl1_qty     = Column(Integer, default=300)
    vl2_qty     = Column(Integer, default=1000)
    rev_no      = Column(Integer, default=0)
    created_at  = Column(DateTime, default=datetime.utcnow)
    updated_at  = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

    bom_lines   = relationship("BomLine",   back_populates="project", cascade="all, delete-orphan")
    qw_prices   = relationship("QwPrice",   back_populates="project", cascade="all, delete-orphan")
    cbom_rows   = relationship("CbomRow",   back_populates="project", cascade="all, delete-orphan")
    nre_lines   = relationship("NreLine",   back_populates="project", cascade="all, delete-orphan")
    ex_inv_rows = relationship("ExInvRow",  back_populates="project", cascade="all, delete-orphan")

class BomLine(Base):
    __tablename__ = "bom_lines"
    id               = Column(Integer, primary_key=True)
    project_id       = Column(Integer, ForeignKey("projects.id"), index=True)
    fg_part          = Column(String(128))   # top-level FG PN
    assembly         = Column(String(128))
    cpn              = Column(String(128), index=True)
    description      = Column(String(512))
    manufacturer     = Column(String(256))
    mpn              = Column(String(256))
    commodity        = Column(String(128))
    qty              = Column(Float)
    uom              = Column(String(32))
    part_status      = Column(String(64))
    ltb_date         = Column(String(32))
    level            = Column(Integer, default=1)
    ref_des          = Column(Text)
    inventory_stock  = Column(Float, default=0)

    project = relationship("Project", back_populates="bom_lines")

class QwPrice(Base):
    __tablename__ = "qw_prices"
    id           = Column(Integer, primary_key=True)
    project_id   = Column(Integer, ForeignKey("projects.id"), index=True)
    cpn          = Column(String(128), index=True)
    mpn          = Column(String(256))
    supp_name    = Column(String(256))
    currency     = Column(String(8))
    cost1_conv   = Column(Float)   # Cost #1 Conv. (USD)
    cost2_conv   = Column(Float)
    cost3_conv   = Column(Float)
    price1_orig  = Column(Float)
    price2_orig  = Column(Float)
    price3_orig  = Column(Float)
    moq          = Column(Integer)
    pkg_qty      = Column(Integer)
    lead_time    = Column(Integer)
    award1       = Column(Integer)
    award2       = Column(Integer)
    award3       = Column(Integer)
    awarded_vol1 = Column(Integer)
    awarded_vol2 = Column(Integer)
    awarded_vol3 = Column(Integer)
    ncnr         = Column(String(16))
    part_status  = Column(String(64))
    payment_term = Column(String(64))
    long_comment = Column(Text)

    project = relationship("Project", back_populates="qw_prices")

class CbomRow(Base):
    __tablename__ = "cbom_rows"
    id               = Column(Integer, primary_key=True)
    project_id       = Column(Integer, ForeignKey("projects.id"), index=True)
    volume           = Column(SAEnum(VolumeType))
    fg_part          = Column(String(128))
    assembly         = Column(String(128))
    cpn              = Column(String(128))
    description      = Column(String(512))
    commodity        = Column(String(128))
    manufacturer     = Column(String(256))
    mpn              = Column(String(256))
    uom              = Column(String(32))
    part_qty         = Column(Float)
    ext_vol_qty      = Column(Float)
    unit_price_conv  = Column(Float)
    price_orig       = Column(Float)
    currency_orig    = Column(String(8))
    ext_price_conv   = Column(Float)
    ext_vol_price    = Column(Float)
    supp_name        = Column(String(256))
    pkg_qty          = Column(Integer)
    moq              = Column(Integer)
    lead_time        = Column(Integer)
    stock            = Column(Float, default=0)
    nre_charge       = Column(Float, default=0)
    nre_charge_conv  = Column(Float, default=0)
    ncnr             = Column(String(16))
    price_control    = Column(String(64))
    scrap_factor     = Column(Float, default=0)
    scrap_qty        = Column(Float, default=0)
    payment_term     = Column(String(64))
    price_status     = Column(String(64))   # Awarded / Lowest / Not Quoted
    price_note       = Column(Text)
    long_comment     = Column(Text)

    project = relationship("Project", back_populates="cbom_rows")

class NreLine(Base):
    __tablename__ = "nre_lines"
    id             = Column(Integer, primary_key=True)
    project_id     = Column(Integer, ForeignKey("projects.id"), index=True)
    nre_type       = Column(String(32))  # MECH_EM / PCB_FAI
    cpn            = Column(String(128))
    description    = Column(String(512))
    commodity      = Column(String(128))
    manufacturer   = Column(String(256))
    mpn            = Column(String(256))
    nre_charge_conv = Column(Float)

    project = relationship("Project", back_populates="nre_lines")

class ExInvRow(Base):
    __tablename__ = "ex_inv_rows"
    id               = Column(Integer, primary_key=True)
    project_id       = Column(Integer, ForeignKey("projects.id"), index=True)
    volume           = Column(SAEnum(VolumeType))
    cpn              = Column(String(128))
    description      = Column(String(512))
    commodity        = Column(String(128))
    manufacturer     = Column(String(256))
    mpn              = Column(String(256))
    part_qty         = Column(Float)
    ext_vol          = Column(Float)
    excess_in_stk    = Column(Float, default=0)
    excess_after_dem = Column(Float, default=0)
    shortage_qty     = Column(Float, default=0)
    scrap            = Column(Float, default=0)
    scrap_factor     = Column(Float, default=0)
    cost_conv        = Column(Float, default=0)
    ext_vol_cost     = Column(Float, default=0)
    supp_name        = Column(String(256))
    pkg_qty          = Column(Integer)
    moq              = Column(Integer)

    project = relationship("Project", back_populates="ex_inv_rows")

class User(Base):
    __tablename__ = "users"
    id           = Column(Integer, primary_key=True)
    username     = Column("name", String(128), unique=True)
    email        = Column(String(256), unique=True)
    hashed_pw    = Column("password", String(512))
    role         = Column(String(32), default="viewer")  # admin / engineer / viewer
    is_active    = Column(Boolean, default=True)
    created_at   = Column(DateTime, default=datetime.utcnow)
